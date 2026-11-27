<?php

use App\Actions\CleanupStaleMcpClients;
use App\Actions\DeleteDemoWorkspace;
use App\Actions\RecordMcpActivity;
use App\Actions\RecordMcpAuthorization;
use App\Mcp\Resources\TaskNetApp;
use App\Models\McpClientAuthorization;
use App\Models\McpDynamicClient;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\StreamedResponse;

// When a workspace approved each MCP client, and when that client last used it.

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    useTemporaryPassportKeys();
    $this->freezeSecond();
    $this->user = User::factory()->demo()->create();
});

function clientRecord(User $user, string $clientId): ?McpClientAuthorization
{
    return McpClientAuthorization::query()->where(['user_id' => $user->id, 'client_id' => $clientId])->first();
}

/**
 * Send one MCP request over HTTP with a real access token.
 *
 * @param  array<string, mixed>  $params
 */
function mcpWithToken(array $tokens, string $method, array $params = []): TestResponse
{
    auth()->forgetGuards();

    $response = mcpRequest(test()->withToken($tokens['access_token']), $method, $params);

    // Read a streamed body now, so everything the server does has happened.
    $response->baseResponse instanceof StreamedResponse && $response->streamedContent();

    test()->withoutHeader('Authorization');

    return $response;
}

function toolCall(array $tokens, string $tool = 'list_projects', array $arguments = []): TestResponse
{
    return mcpWithToken($tokens, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
}

function refreshTokens(array $tokens): array
{
    $response = test()->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $tokens['client_id'],
    ])->assertOk();

    return [...$tokens, 'access_token' => $response->json('access_token'), 'refresh_token' => $response->json('refresh_token')];
}

/**
 * Open the consent screen and return the token its forms submit.
 */
function consentToken(User $user, $client): string
{
    $screen = test()->actingAs($user)->get(authorizeUrl($client))->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $screen->getContent(), $matches);

    return $matches[1];
}

// --- Approval date ------------------------------------------------------------

test('registering a client records nothing, and approving it records the time of approval', function () {
    $client = registerTestClient();

    expect(McpClientAuthorization::query()->count())->toBe(0);

    $this->travel(3)->days();
    issueOAuthTokens($this->user, $client);

    $record = clientRecord($this->user, $client->getKey());

    expect($record->authorized_at->equalTo(now()))->toBeTrue()
        ->and($record->authorized_at->equalTo(McpDynamicClient::query()->find($client->getKey())->created_at))->toBeFalse()
        ->and($record->last_used_at)->toBeNull();
});

test('the approval is recorded when the user approves, before any token is issued', function () {
    $client = registerTestClient();

    requestAuthorizationCode($this->user, $client, Str::random(64));

    expect(clientRecord($this->user, $client->getKey())?->authorized_at)->not->toBeNull()
        ->and(Passport::token()->newQuery()->count())->toBe(0);
});

test('seeing the consent screen or denying it records nothing', function () {
    $client = registerTestClient();
    $authToken = consentToken($this->user, $client);

    expect(McpClientAuthorization::query()->count())->toBe(0);

    $this->delete(route('passport.authorizations.deny'), ['state' => 'xyz', 'client_id' => $client->getKey(), 'auth_token' => $authToken])
        ->assertRedirect();

    expect(McpClientAuthorization::query()->count())->toBe(0);
});

test('an approval with a wrong token is refused and records nothing', function () {
    $client = registerTestClient();
    consentToken($this->user, $client);

    $response = $this->post(route('passport.authorizations.approve'), ['client_id' => $client->getKey(), 'auth_token' => 'forged']);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and(McpClientAuthorization::query()->count())->toBe(0);
});

test('the record follows the validated authorization request, not the submitted client', function () {
    $approved = registerTestClient();
    $other = registerTestClient();
    $authToken = consentToken($this->user, $approved);

    $this->post(route('passport.authorizations.approve'), ['client_id' => $other->getKey(), 'auth_token' => $authToken])
        ->assertRedirect();

    expect(McpClientAuthorization::query()->pluck('client_id')->all())->toBe([$approved->getKey()]);
});

test('renewing tokens changes neither date', function () {
    $tokens = issueOAuthTokens($this->user);
    $approvedAt = now();

    $this->travel(2)->days();
    refreshTokens($tokens);

    $record = clientRecord($this->user, $tokens['client_id']);

    expect($record->authorized_at->equalTo($approvedAt))->toBeTrue()
        ->and($record->last_used_at)->toBeNull();
});

test('reconnecting without a new approval keeps the date', function () {
    $client = registerTestClient();
    issueOAuthTokens($this->user, $client);
    $approvedAt = now();

    // The access token is still live, so Passport skips the consent screen.
    $this->travel(1)->hours();
    $this->actingAs($this->user)->get(authorizeUrl($client))->assertRedirect();

    expect(clientRecord($this->user, $client->getKey())->authorized_at->equalTo($approvedAt))->toBeTrue();
});

test('approving again moves the date and keeps one record', function () {
    $client = registerTestClient();
    issueOAuthTokens($this->user, $client);

    // The access token has lapsed, so the consent screen is shown again.
    $this->travel(5)->days();
    issueOAuthTokens($this->user, $client);

    expect(McpClientAuthorization::query()->count())->toBe(1)
        ->and(clientRecord($this->user, $client->getKey())->authorized_at->equalTo(now()))->toBeTrue();
});

test('a failure to record the date does not undo the approval', function () {
    Exceptions::fake();
    $this->mock(RecordMcpAuthorization::class)->shouldReceive('record')->andThrow(new RuntimeException('unavailable'));

    $tokens = issueOAuthTokens($this->user);

    expect($tokens['access_token'])->not->toBeEmpty();
    Exceptions::assertReported(RuntimeException::class);
});

// --- Last activity ------------------------------------------------------------

test('a successful tool call records activity', function () {
    $tokens = issueOAuthTokens($this->user);

    $this->travel(1)->hours();
    toolCall($tokens)->assertOk();

    expect(clientRecord($this->user, $tokens['client_id'])->last_used_at->equalTo(now()))->toBeTrue();
});

test('reading the interactive view records activity', function () {
    $tokens = issueOAuthTokens($this->user);

    mcpWithToken($tokens, 'resources/read', ['uri' => app(TaskNetApp::class)->uri()])->assertOk();

    expect(clientRecord($this->user, $tokens['client_id'])->last_used_at)->not->toBeNull();
});

test('connecting, listing and pinging are not activity', function (string $method, array $params) {
    $tokens = issueOAuthTokens($this->user);

    mcpWithToken($tokens, $method, $params)->assertOk();

    expect(clientRecord($this->user, $tokens['client_id'])->last_used_at)->toBeNull();
})->with([
    'initialize' => ['initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']]],
    'tools/list' => ['tools/list', []],
    'resources/list' => ['resources/list', []],
    'ping' => ['ping', []],
]);

test('operations that fail are not activity', function (string $method, array $params) {
    $tokens = issueOAuthTokens($this->user);

    mcpWithToken($tokens, $method, $params);

    expect(clientRecord($this->user, $tokens['client_id'])->last_used_at)->toBeNull();
})->with([
    'a tool that reports an error' => ['tools/call', ['name' => 'get_project', 'arguments' => ['project_id' => 999999]]],
    'invalid tool input' => ['tools/call', ['name' => 'create_project', 'arguments' => ['name' => '']]],
    'an unknown tool' => ['tools/call', ['name' => 'delete_project', 'arguments' => []]],
    'an unknown resource' => ['resources/read', ['uri' => 'ui://tasknet/nothing']],
    'an unknown method' => ['nothing/here', []],
]);

test('rejected requests are not activity', function (Closure $reject, int $status) {
    $tokens = issueOAuthTokens($this->user);
    $reject($this->user);

    toolCall($tokens)->assertStatus($status);

    expect(McpClientAuthorization::query()->whereNotNull('last_used_at')->count())->toBe(0);
})->with([
    'a revoked token' => [fn (User $user) => Passport::token()->newQuery()->where('user_id', $user->id)->update(['revoked' => true]), 401],
    'an expired workspace' => [fn (User $user) => $user->forceFill(['expires_at' => now()->subMinute()])->save(), 403],
]);

test('a rate-limited request is not activity', function () {
    config(['tasknet.mcp.requests_per_minute' => 1]);
    $tokens = issueOAuthTokens($this->user);

    mcpWithToken($tokens, 'ping')->assertOk();
    toolCall($tokens)->assertTooManyRequests();

    expect(clientRecord($this->user, $tokens['client_id'])->last_used_at)->toBeNull();
});

test('activity is written at most once per interval', function () {
    $tokens = issueOAuthTokens($this->user);
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^(insert|update|delete).*mcp_client_authorizations/i', $query->sql)) {
            $writes++;
        }
    });

    toolCall($tokens);
    $first = now();

    $this->travel(4)->minutes();
    toolCall($tokens);
    toolCall($tokens);

    expect($writes)->toBe(1)
        ->and(clientRecord($this->user, $tokens['client_id'])->last_used_at->equalTo($first))->toBeTrue();

    $this->travel(2)->minutes();
    toolCall($tokens);

    expect($writes)->toBe(2)
        ->and(clientRecord($this->user, $tokens['client_id'])->last_used_at->equalTo(now()))->toBeTrue();
});

test('activity belongs to one workspace and one client', function () {
    $other = User::factory()->demo()->create();
    $shared = registerTestClient();
    $mine = issueOAuthTokens($this->user, $shared);
    issueOAuthTokens($other, $shared);
    $second = issueOAuthTokens($this->user);

    toolCall($mine)->assertOk();

    expect(clientRecord($this->user, $shared->getKey())->last_used_at)->not->toBeNull()
        ->and(clientRecord($other, $shared->getKey())->last_used_at)->toBeNull()
        ->and(clientRecord($this->user, $second['client_id'])->last_used_at)->toBeNull();
});

test('a client approved before dates were kept gets activity but no invented approval date', function () {
    $tokens = issueOAuthTokens($this->user);
    McpClientAuthorization::query()->delete();

    $tokens = refreshTokens($tokens);
    expect(McpClientAuthorization::query()->count())->toBe(0);

    toolCall($tokens)->assertOk();

    $record = clientRecord($this->user, $tokens['client_id']);

    expect($record->authorized_at)->toBeNull()
        ->and($record->last_used_at->equalTo(now()))->toBeTrue();
});

test('a later activity update keeps the approval date', function () {
    $tokens = issueOAuthTokens($this->user);
    $approvedAt = now();

    $this->travel(1)->hours();
    toolCall($tokens);
    $this->travel(1)->hours();
    toolCall($tokens);

    expect(clientRecord($this->user, $tokens['client_id'])->authorized_at->equalTo($approvedAt))->toBeTrue();
});

test('a failure to record activity does not fail the operation', function () {
    Exceptions::fake();
    $tokens = issueOAuthTokens($this->user);
    $this->mock(RecordMcpActivity::class)->shouldReceive('record')->andThrow(new RuntimeException('unavailable'));

    $response = toolCall($tokens)->assertOk();

    expect(mcpResult($response)['isError'])->toBeFalse();
    Exceptions::assertReported(RuntimeException::class);
});

// --- Removal ------------------------------------------------------------------

test('revoking removes the record for that workspace and client only', function () {
    $other = User::factory()->demo()->create();
    $shared = registerTestClient();
    issueOAuthTokens($this->user, $shared);
    issueOAuthTokens($other, $shared);
    $kept = issueOAuthTokens($this->user);

    $this->actingAs($this->user)->delete(route('settings.clients.destroy', $shared->getKey()))->assertRedirect(route('settings'));

    expect(clientRecord($this->user, $shared->getKey()))->toBeNull()
        ->and(clientRecord($other, $shared->getKey()))->not->toBeNull()
        ->and(clientRecord($this->user, $kept['client_id']))->not->toBeNull();
});

test('approving a revoked client again starts a new record', function () {
    $client = registerTestClient();
    issueOAuthTokens($this->user, $client);
    app(RecordMcpActivity::class)->record($this->user, $client->getKey());

    $this->actingAs($this->user)->delete(route('settings.clients.destroy', $client->getKey()));
    auth()->guard('web')->forgetUser();
    $this->flushSession();

    $this->travel(1)->days();
    issueOAuthTokens($this->user, $client);

    $record = clientRecord($this->user, $client->getKey());

    expect($record->authorized_at->equalTo(now()))->toBeTrue()
        ->and($record->last_used_at)->toBeNull();
});

test('deleting a workspace removes its records', function () {
    $other = User::factory()->demo()->create();
    issueOAuthTokens($this->user);
    issueOAuthTokens($other);

    app(DeleteDemoWorkspace::class)->delete($this->user);

    expect(McpClientAuthorization::query()->pluck('user_id')->all())->toBe([$other->id]);
});

test('cleaning up a stale client removes its records', function () {
    $tokens = issueOAuthTokens($this->user);
    McpDynamicClient::query()->update(['created_at' => now()->subYear()]);
    Passport::token()->newQuery()->delete();
    Passport::authCode()->newQuery()->delete();

    expect(app(CleanupStaleMcpClients::class)->cleanup())->toBe(1)
        ->and(McpClientAuthorization::query()->where('client_id', $tokens['client_id'])->exists())->toBeFalse();
});
