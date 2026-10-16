<?php

use App\Mcp\Tools\CreateTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

function registerRedirect(string $uri): TestResponse
{
    return test()->postJson('/oauth/register', ['client_name' => 'Agent', 'redirect_uris' => [$uri]]);
}

// --- OAuth redirect URIs ---------------------------------------------------

test('loopback callbacks are accepted for local clients', function (string $uri) {
    registerRedirect($uri)->assertCreated()->assertJsonPath('redirect_uris.0', $uri);
})->with([
    'localhost' => 'http://localhost:6274/oauth/callback',
    '127.0.0.1' => 'http://127.0.0.1:7777/callback',
]);

test('a configured production domain is accepted', function () {
    config()->set('mcp.redirect_domains', ['http://localhost', 'https://client.example.com']);

    registerRedirect('https://client.example.com/oauth/callback')->assertCreated();
});

test('arbitrary domains are rejected', function (string $uri) {
    registerRedirect($uri)->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    expect(Client::query()->where('name', 'Agent')->exists())->toBeFalse();
})->with([
    'attacker' => 'https://attacker.example/callback',
    'look-alike of localhost' => 'https://localhost.attacker.example/callback',
    'https localhost' => 'https://localhost/callback',
]);

test('look-alike hosts of a configured domain are rejected', function () {
    config()->set('mcp.redirect_domains', ['https://client.example.com']);

    registerRedirect('https://client.example.com.attacker.example/cb')->assertStatus(400);
    registerRedirect('http://client.example.com/cb')->assertStatus(400);
});

test('the wildcard is no longer part of the redirect configuration', function () {
    expect(config('mcp.redirect_domains'))->not->toContain('*');

    $previous = [getenv('MCP_REDIRECT_DOMAINS'), $_ENV['MCP_REDIRECT_DOMAINS'] ?? null, $_SERVER['MCP_REDIRECT_DOMAINS'] ?? null];

    // env() prefers values already loaded from .env, so override every source it reads.
    $wildcard = '*,https://client.example.com';
    putenv("MCP_REDIRECT_DOMAINS={$wildcard}");
    $_ENV['MCP_REDIRECT_DOMAINS'] = $_SERVER['MCP_REDIRECT_DOMAINS'] = $wildcard;

    try {
        $domains = (require config_path('mcp.php'))['redirect_domains'];
    } finally {
        $previous[0] === false ? putenv('MCP_REDIRECT_DOMAINS') : putenv("MCP_REDIRECT_DOMAINS={$previous[0]}");
        $previous[1] === null ? $_ENV = array_diff_key($_ENV, ['MCP_REDIRECT_DOMAINS' => 1]) : $_ENV['MCP_REDIRECT_DOMAINS'] = $previous[1];
        $previous[2] === null ? $_SERVER = array_diff_key($_SERVER, ['MCP_REDIRECT_DOMAINS' => 1]) : $_SERVER['MCP_REDIRECT_DOMAINS'] = $previous[2];
    }

    expect($domains)->toBe(['https://client.example.com']);
});

// --- Consent screen ---------------------------------------------------------

function consentScreenFor(string $name, string $redirect, User $user): TestResponse
{
    $client = Client::query()->findOrFail(
        test()->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [$redirect]])->assertCreated()->json('client_id'),
    );

    return test()->actingAs($user)->get(route('passport.authorizations.authorize', [
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]));
}

test('the consent screen shows only the host the client will return to', function () {
    config()->set('mcp.redirect_domains', ['https://client.example.com']);
    $user = User::factory()->demo()->create();

    consentScreenFor('Agent', 'https://client.example.com/cb/path?secret=1', $user)
        ->assertOk()
        ->assertSee('Returning to')
        ->assertSee('client.example.com')
        ->assertDontSee('secret=1', false)
        ->assertDontSee('/cb/path', false);
});

test('localhost is shown clearly during local development', function () {
    $user = User::factory()->demo()->create();

    consentScreenFor('Agent', 'http://localhost:6274/oauth/callback', $user)
        ->assertOk()
        ->assertSee('Returning to')
        ->assertSee('localhost');
});

test('markup in client metadata is escaped on the consent screen', function () {
    $user = User::factory()->demo()->create();

    $response = consentScreenFor('<script>alert(1)</script><b>Evil</b>', 'http://localhost:6274/cb', $user)->assertOk();

    expect($response->getContent())
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<b>Evil</b>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('the consent screen never shows the synthetic email', function () {
    $user = User::factory()->demo()->create();

    consentScreenFor('Agent', 'http://127.0.0.1:7777/cb', $user)
        ->assertOk()
        ->assertSee('127.0.0.1')
        ->assertDontSee($user->email)
        ->assertDontSee('tasknet.local');
});

// --- Security headers -------------------------------------------------------

test('responses carry anti-framing and nosniff headers', function (string $method, string $uri) {
    $this->withoutVite();

    $response = $this->call($method, $uri);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'none'");
})->with([
    'home page' => ['GET', '/'],
    'login page' => ['GET', '/login'],
    'redirect' => ['GET', '/dashboard'],
    'not found' => ['GET', '/does-not-exist'],
    'oauth metadata' => ['GET', '/.well-known/oauth-authorization-server'],
    'unauthenticated mcp' => ['POST', '/mcp'],
]);

test('the consent screen cannot be framed', function () {
    $user = User::factory()->demo()->create();

    consentScreenFor('Agent', 'http://localhost:6274/cb', $user)
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

// --- Strict due_date --------------------------------------------------------

test('due_date accepts only YYYY-MM-DD on the web', function (string $value, bool $valid) {
    $this->withoutVite();
    $user = User::factory()->demo()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this->actingAs($user)->from(route('projects.tasks.create', $project))
        ->post(route('projects.tasks.store', $project), ['title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'due_date' => $value]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('due_date');
    expect($project->tasks()->count())->toBe($valid ? 1 : 0);
})->with([
    'valid' => ['2026-10-11', true],
    'oversized year' => ['999999-01-01', false],
    'five digit year' => ['10000-01-01', false],
    'slashes' => ['2026/10/11', false],
    'day first' => ['11-10-2026', false],
    'with time' => ['2026-10-11 10:00:00', false],
    'impossible day' => ['2026-02-30', false],
    'relative word' => ['tomorrow', false],
]);

test('MCP applies the same due_date rule', function (string $value, bool $valid) {
    $user = User::factory()->demo()->create();
    $project = Project::factory()->for($user)->create();
    $args = ['project_id' => $project->id, 'title' => 'T', 'due_date' => $value];

    $direct = callMcpTool($user, CreateTaskTool::class, $args);
    $valid ? $direct->assertOk() : $direct->assertHasErrors();

    expect($project->tasks()->count())->toBe($valid ? 1 : 0);
})->with([
    'valid' => ['2026-10-11', true],
    'oversized year' => ['999999-01-01', false],
    'slashes' => ['2026/10/11', false],
    'with time' => ['2026-10-11T10:00:00Z', false],
]);

test('create_task rejects malformed due dates over HTTP', function () {
    $user = User::factory()->demo()->create();
    $project = Project::factory()->for($user)->create();
    Passport::actingAs($user, ['mcp:use'], 'api');

    $bad = callMcpToolOverHttp($this, 'create_task', ['project_id' => $project->id, 'title' => 'T', 'due_date' => '999999-01-01'])->assertOk();
    $good = callMcpToolOverHttp($this, 'create_task', ['project_id' => $project->id, 'title' => 'T', 'due_date' => '2026-10-11'])->assertOk();

    expect(mcpResult($bad)['isError'])->toBeTrue()
        ->and(mcpResult($good)['isError'])->toBeFalse()
        ->and(Task::where('project_id', $project->id)->sole()->due_date->toDateString())->toBe('2026-10-11');
});

// --- MCP rate limit ---------------------------------------------------------

test('each workspace has an independent MCP rate limit', function () {
    config()->set('tasknet.mcp.requests_per_minute', 3);
    $first = User::factory()->demo()->create();
    $second = User::factory()->demo()->create();

    Passport::actingAs($first, ['mcp:use'], 'api');
    foreach (range(1, 3) as $ignored) {
        mcpRequest($this, 'tools/list')->assertOk();
    }
    mcpRequest($this, 'tools/list')->assertStatus(429)->assertHeader('Retry-After');

    Passport::actingAs($second, ['mcp:use'], 'api');
    foreach (range(1, 3) as $ignored) {
        mcpRequest($this, 'tools/list')->assertOk();
    }

    Passport::actingAs($first, ['mcp:use'], 'api');
    mcpRequest($this, 'tools/list')->assertStatus(429);
});

test('the default MCP rate limit leaves room for normal use', function () {
    expect(config('tasknet.mcp.requests_per_minute'))->toBeGreaterThanOrEqual(60);
});

test('rate limiting leaves MCP authentication unchanged', function () {
    config()->set('tasknet.mcp.requests_per_minute', 1);

    foreach (range(1, 3) as $ignored) {
        mcpRequest($this, 'tools/list')->assertUnauthorized();
    }

    $expired = User::factory()->demo()->expired()->create();
    Passport::actingAs($expired, ['mcp:use'], 'api');
    mcpRequest($this, 'tools/list')->assertForbidden();

    Passport::actingAs(User::factory()->demo()->create(), [], 'api');
    mcpRequest($this, 'tools/list')->assertForbidden();
});

test('OAuth discovery is not affected by the MCP rate limit', function () {
    config()->set('tasknet.mcp.requests_per_minute', 1);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
    }
});
