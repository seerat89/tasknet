<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();

    $this->project = Project::factory()->for($this->user)->create(['name' => 'Launch plan']);
    Project::factory()->for($this->otherUser)->create(['name' => 'Secret roadmap']);
});

afterEach(function () {
    Passport::$keyPath = null;

    if (isset($this->keyPath)) {
        File::deleteDirectory($this->keyPath);
    }
});

/**
 * Send a JSON-RPC request to the MCP endpoint.
 *
 * @param  array<string, mixed>  $params
 */
function mcpRequest(mixed $test, string $method, array $params = []): TestResponse
{
    return $test->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => (object) $params,
    ]);
}

/**
 * Use throwaway OAuth keys so tests do not depend on files in storage/.
 */
function useTemporaryPassportKeys(): void
{
    $path = test()->keyPath = sys_get_temp_dir().'/tasknet-passport-'.Str::random(12);
    File::ensureDirectoryExists($path);

    Passport::loadKeysFrom($path);
    test()->artisan('passport:keys', ['--length' => 2048])->assertSuccessful();
}

test('unauthenticated requests to the MCP endpoint are rejected', function () {
    mcpRequest($this, 'tools/list')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('a bogus bearer token is rejected', function () {
    $this->withToken('not-a-real-token')
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});

test('a web session alone does not grant MCP access', function () {
    $this->actingAs($this->user)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});

test('a token without the MCP scope is rejected', function () {
    Passport::actingAs($this->user, [], 'api');

    mcpRequest($this, 'tools/list')->assertForbidden();
});

test('an authenticated OAuth user can list exactly the eight tools', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $tools = mcpRequest($this, 'tools/list')->assertOk()->json('result.tools.*.name');

    expect($tools)->toBe([
        'list_projects', 'get_project', 'create_project', 'update_project',
        'list_tasks', 'get_task', 'create_task', 'update_task',
    ]);
});

test('an authenticated OAuth user only sees their own workspace', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = mcpRequest($this, 'tools/call', ['name' => 'list_projects', 'arguments' => (object) []])->assertOk();

    expect($response->json('result.structuredContent.projects'))->toHaveCount(1)
        ->and($response->json('result.structuredContent.projects.0.name'))->toBe('Launch plan')
        ->and($response->getContent())->not->toContain('Secret roadmap');
});

test('creating a project over HTTP uses the OAuth user and the existing limits', function () {
    config()->set('tasknet.limits.projects', 2);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    mcpRequest($this, 'tools/call', ['name' => 'create_project', 'arguments' => ['name' => 'Second', 'user_id' => $this->otherUser->id]])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    $limited = mcpRequest($this, 'tools/call', ['name' => 'create_project', 'arguments' => ['name' => 'Third']])->assertOk();

    expect($limited->json('result.isError'))->toBeTrue()
        ->and($limited->getContent())->toContain('You can create up to 2 projects')
        ->and($this->user->projects()->count())->toBe(2)
        ->and($this->otherUser->projects()->count())->toBe(1);
});

test('an expired demo workspace cannot use MCP even with a valid token', function () {
    $expired = User::factory()->demo()->expired()->create();
    Passport::actingAs($expired, ['mcp:use'], 'api');

    mcpRequest($this, 'tools/list')->assertForbidden();
    mcpRequest($this, 'tools/call', ['name' => 'create_project', 'arguments' => ['name' => 'Too late']])->assertForbidden();

    expect($expired->projects()->count())->toBe(0);
});

test('OAuth discovery advertises only the authorization code and refresh token grants', function () {
    $metadata = $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    expect($metadata['grant_types_supported'])->toBe(['authorization_code', 'refresh_token'])
        ->and($metadata['code_challenge_methods_supported'])->toBe(['S256'])
        ->and($metadata['scopes_supported'])->toBe(['mcp:use']);

    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('scopes_supported', ['mcp:use']);
});

test('the password and device grants are not available', function () {
    expect(Route::has('passport.device'))->toBeFalse();

    $this->postJson('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => 'any',
        'username' => $this->user->demo_id,
        'password' => 'secret',
    ])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
});

test('MCP clients can register as public OAuth clients', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Test Agent',
        'redirect_uris' => ['http://localhost:7777/callback'],
    ])->assertCreated()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use');

    expect(Client::query()->where('name', 'Test Agent')->exists())->toBeTrue();
});

test('the authorization screen requires a signed-in demo user', function () {
    $client = registerTestClient();

    $this->get(authorizeUrl($client))->assertRedirect(route('login'));
});

test('the authorization screen names the demo id and client but not the internal email', function () {
    $client = registerTestClient();

    $this->actingAs($this->user)
        ->get(authorizeUrl($client))
        ->assertOk()
        ->assertSee($this->user->demo_id)
        ->assertSee('Test Agent')
        ->assertSee('Approve')
        ->assertSee('Deny')
        ->assertDontSee($this->user->email)
        ->assertDontSee('tasknet.local');
});

test('an expired demo user cannot reach the authorization screen', function () {
    $expired = User::factory()->demo()->expired()->create();
    $client = registerTestClient();

    $this->actingAs($expired)
        ->get(authorizeUrl($client))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('denying an authorization request returns access_denied to the client', function () {
    $client = registerTestClient();

    $screen = $this->actingAs($this->user)->get(authorizeUrl($client))->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $screen->getContent(), $matches);

    $response = $this->delete(route('passport.authorizations.deny'), [
        'state' => 'xyz',
        'client_id' => $client->getKey(),
        'auth_token' => $matches[1],
    ]);

    expect($response->headers->get('Location'))->toContain('error=access_denied');
});

test('the full OAuth flow issues a token that works only for the approving user', function () {
    useTemporaryPassportKeys();

    $client = registerTestClient();
    $verifier = Str::random(64);

    $screen = $this->actingAs($this->user)->get(authorizeUrl($client, $verifier))->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $screen->getContent(), $matches);

    $approval = $this->post(route('passport.authorizations.approve'), [
        'state' => 'xyz',
        'client_id' => $client->getKey(),
        'auth_token' => $matches[1],
    ]);

    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('xyz')->and($query)->toHaveKey('code');

    auth()->forgetGuards();

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:7777/callback',
        'code' => $query['code'],
        'code_verifier' => $verifier,
    ])->assertOk()->json();

    expect($token)->toHaveKeys(['access_token', 'refresh_token'])
        ->and($token['access_token'])->not->toBe($this->user->password);

    auth()->forgetGuards();
    $this->flushSession();

    $response = $this->withToken($token['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_projects', 'arguments' => (object) []]])
        ->assertOk();

    expect($response->json('result.structuredContent.projects.0.name'))->toBe('Launch plan')
        ->and($response->getContent())->not->toContain('Secret roadmap');

    // The same token stops working once the workspace expires.
    $this->user->forceFill(['expires_at' => now()->subMinute()])->save();
    auth()->forgetGuards();

    $this->withToken($token['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertForbidden();
});

function registerTestClient(): Client
{
    $response = test()->postJson('/oauth/register', [
        'client_name' => 'Test Agent',
        'redirect_uris' => ['http://localhost:7777/callback'],
    ])->assertCreated();

    return Client::query()->findOrFail($response->json('client_id'));
}

function authorizeUrl(Client $client, string $verifier = 'verifier'): string
{
    return route('passport.authorizations.authorize', [
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:7777/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);
}
