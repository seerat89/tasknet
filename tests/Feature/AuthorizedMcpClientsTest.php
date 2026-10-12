<?php

use App\Actions\ListAuthorizedMcpClients;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Create a registered, unowned OAuth client like the dynamic registration endpoint does.
 */
function namedClient(string $name): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['http://localhost:7777/callback'], false);
}

/**
 * Insert an access token row directly, to set up a specific token state.
 *
 * @param  array<string, mixed>  $attributes
 */
function tokenRow(User $user, Client $client, array $attributes = []): string
{
    $id = Str::random(80);

    Passport::token()->forceFill([
        'id' => $id,
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
        ...$attributes,
    ])->save();

    return $id;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function refreshRow(string $accessTokenId, array $attributes = []): string
{
    $id = Str::random(80);

    Passport::refreshToken()->forceFill([
        'id' => $id,
        'access_token_id' => $accessTokenId,
        'revoked' => false,
        'expires_at' => now()->addDays(30),
        ...$attributes,
    ])->save();

    return $id;
}

function bearerStatus(string $accessToken): int
{
    auth()->forgetGuards();

    $status = test()->withToken($accessToken)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->getStatusCode();

    test()->withoutHeader('Authorization');

    return $status;
}

function refreshStatus(array $tokens): int
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $tokens['client_id'],
    ])->getStatusCode();
}

function authorizedNames(User $user): array
{
    return app(ListAuthorizedMcpClients::class)->get($user)->pluck('name')->all();
}

// --- Listing ------------------------------------------------------------------

test('guests cannot view the settings or revoke clients', function () {
    $client = namedClient('Agent');

    $this->get(route('settings'))->assertRedirect(route('login'));
    $this->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('login'));
});

test('a workspace with no authorization sees the empty state', function () {
    $this->actingAs(User::factory()->demo()->create())->get(route('settings'))
        ->assertOk()
        ->assertSeeText(['Authorized MCP clients', 'Clients you’ve approved to access this workspace through MCP.', 'No MCP clients are currently authorized.', 'Connect a compatible MCP client and approve access to see it here.'])
        ->assertDontSeeText('Revoke access');
});

test('the section sits between Workspace and the Danger zone', function () {
    $this->actingAs(User::factory()->demo()->create())->get(route('settings'))
        ->assertSeeInOrder(['Workspace settings', 'Demo ID', 'Authorized MCP clients', 'Danger zone', 'Delete workspace']);
});

test('an authorized client appears with a revoke button and no secrets', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);

    $this->actingAs($user)->get(route('settings'))
        ->assertOk()
        ->assertSeeText(['Test Agent', 'Revoke access'])
        ->assertDontSeeText('No MCP clients are currently authorized.')
        ->assertDontSee($tokens['access_token'])
        ->assertDontSee($tokens['refresh_token'])
        ->assertDontSeeText($tokens['client_id'])
        ->assertDontSee('http://localhost:7777/callback', false)
        ->assertDontSee($user->email)
        ->assertDontSeeText(['Authorized Oct', 'online', 'currently connected']);
});

test('client names are escaped and a missing name falls back', function () {
    $user = User::factory()->demo()->create();
    tokenRow($user, namedClient('<script>alert(1)</script><b>Evil</b>'));
    tokenRow($user, namedClient(''));

    $response = $this->actingAs($user)->get(route('settings'))->assertOk();

    expect($response->getContent())
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<b>Evil</b>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    $response->assertSeeText('MCP client');
});

test('several token chains for one client give one row', function () {
    $user = User::factory()->demo()->create();
    $client = namedClient('Multi Chain Client');
    $first = tokenRow($user, $client);
    refreshRow($first);
    refreshRow(tokenRow($user, $client));
    tokenRow($user, $client);

    $response = $this->actingAs($user)->get(route('settings'))->assertOk();

    expect(substr_count($response->getContent(), 'Revoke access for Multi Chain Client?'))->toBe(1);
});

test('another workspace’s client does not appear', function () {
    $user = User::factory()->demo()->create();
    $other = User::factory()->demo()->create();
    tokenRow($other, namedClient('Someone Elses Client'));

    $this->actingAs($user)->get(route('settings'))->assertOk()->assertDontSeeText('Someone Elses Client');
});

test('token history that can no longer grant access is not listed', function (Closure $setup) {
    $user = User::factory()->demo()->create();
    $client = namedClient('History Client');
    $setup($user, $client);

    expect(authorizedNames($user))->toBe([]);
    $this->actingAs($user)->get(route('settings'))->assertDontSeeText('History Client');
})->with([
    'revoked access token' => [fn ($u, $c) => tokenRow($u, $c, ['revoked' => true])],
    'expired access token only' => [fn ($u, $c) => tokenRow($u, $c, ['expires_at' => now()->subDay()])],
    'expired token with a revoked refresh token' => [fn ($u, $c) => refreshRow(tokenRow($u, $c, ['expires_at' => now()->subDay()]), ['revoked' => true])],
    'expired token with an expired refresh token' => [fn ($u, $c) => refreshRow(tokenRow($u, $c, ['expires_at' => now()->subDays(40)]), ['expires_at' => now()->subDay()])],
    'revoked token with a revoked refresh token' => [fn ($u, $c) => refreshRow(tokenRow($u, $c, ['revoked' => true]), ['revoked' => true])],
    'revoked client registration' => [function ($u, $c) {
        tokenRow($u, $c);
        $c->forceFill(['revoked' => true])->save();
    }],
]);

test('a client whose access token expired is listed while its refresh token still works', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);

    Passport::token()->newQuery()->where('user_id', $user->id)->update(['expires_at' => now()->subHour()]);

    expect(authorizedNames($user))->toBe(['Test Agent']);
    expect(refreshStatus($tokens))->toBe(200);
    expect(authorizedNames($user))->toBe(['Test Agent']);
});

test('the scheduled purge keeps expired access tokens until their refresh tokens expire', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'passport:purge'));

    expect($event)->not->toBeNull()
        ->and($event->command)->toContain('--hours='.(config('tasknet.demo.lifetime_days') * 24));
});

// --- Revocation ---------------------------------------------------------------

test('revoking stops the old tokens, removes the row and keeps the client registration', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);

    expect(bearerStatus($tokens['access_token']))->toBe(200);

    $this->actingAs($user)->delete(route('settings.clients.destroy', $tokens['client_id']))
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Access revoked for Test Agent.');

    expect(bearerStatus($tokens['access_token']))->toBe(401)
        ->and(refreshStatus($tokens))->toBeGreaterThanOrEqual(400)
        ->and(authorizedNames($user))->toBe([])
        ->and(Passport::token()->newQuery()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Passport::client()->newQuery()->whereKey($tokens['client_id'])->exists())->toBeTrue();

    $this->actingAs($user)->get(route('settings'))->assertSeeText('No MCP clients are currently authorized.');
});

test('two workspaces sharing one client are independent when one revokes', function () {
    useTemporaryPassportKeys();
    $a = User::factory()->demo()->create();
    $b = User::factory()->demo()->create();
    $client = registerTestClient();
    $tokensA = issueOAuthTokens($a, $client);
    $tokensB = issueOAuthTokens($b, $client);

    $this->actingAs($a)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));

    expect(bearerStatus($tokensA['access_token']))->toBe(401)
        ->and(refreshStatus($tokensA))->toBeGreaterThanOrEqual(400)
        ->and(bearerStatus($tokensB['access_token']))->toBe(200)
        ->and(authorizedNames($b))->toBe(['Test Agent'])
        ->and(Passport::client()->newQuery()->whereKey($client->getKey())->exists())->toBeTrue()
        ->and(refreshStatus($tokensB))->toBe(200);
});

test('a client that only another workspace authorized cannot be revoked', function () {
    useTemporaryPassportKeys();
    $a = User::factory()->demo()->create();
    $b = User::factory()->demo()->create();
    $tokensB = issueOAuthTokens($b);

    $this->actingAs($a)->delete(route('settings.clients.destroy', $tokensB['client_id']))->assertNotFound();

    expect(bearerStatus($tokensB['access_token']))->toBe(200)
        ->and(authorizedNames($b))->toBe(['Test Agent']);

    $this->actingAs($a)->delete(route('settings.clients.destroy', (string) Str::uuid()))->assertNotFound();
});

test('every token chain for the user and client is revoked', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $client = registerTestClient();
    $first = issueOAuthTokens($user, $client);
    $second = issueOAuthTokens($user, $client);

    $this->actingAs($user)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));

    expect(bearerStatus($first['access_token']))->toBe(401)
        ->and(bearerStatus($second['access_token']))->toBe(401)
        ->and(refreshStatus($first))->toBeGreaterThanOrEqual(400)
        ->and(refreshStatus($second))->toBeGreaterThanOrEqual(400);
});

test('revoking only affects the chosen client', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $kept = issueOAuthTokens($user);
    $revoked = issueOAuthTokens($user);

    $this->actingAs($user)->delete(route('settings.clients.destroy', $revoked['client_id']))->assertRedirect(route('settings'));

    expect(bearerStatus($revoked['access_token']))->toBe(401)
        ->and(bearerStatus($kept['access_token']))->toBe(200)
        ->and(refreshStatus($kept))->toBe(200);
});

test('an authorization code that was not exchanged yet is invalidated', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $client = registerTestClient();
    issueOAuthTokens($user, $client);

    $verifier = Str::random(64);
    $pendingCode = requestAuthorizationCode($user, $client, $verifier);
    expect(Passport::authCode()->newQuery()->where('user_id', $user->id)->where('client_id', $client->getKey())->where('revoked', false)->exists())->toBeTrue();

    $this->actingAs($user)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));

    expect(Passport::authCode()->newQuery()->where('user_id', $user->id)->count())->toBe(0);
    expect(exchangeAuthorizationCode($client, $pendingCode, $verifier)->getStatusCode())->toBeGreaterThanOrEqual(400);
});

test('a revoked client can be authorized again through the normal flow', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $client = registerTestClient();
    $old = issueOAuthTokens($user, $client);

    $this->actingAs($user)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));
    expect(authorizedNames($user))->toBe([]);

    // The normal consent screen is shown again rather than silently re-approving.
    $this->actingAs($user)->get(authorizeUrl($client))->assertOk()->assertSeeText(['Approve', 'Deny']);
    auth()->guard('web')->forgetUser();
    $this->flushSession();

    $new = issueOAuthTokens($user, $client);

    expect(bearerStatus($new['access_token']))->toBe(200)
        ->and(bearerStatus($old['access_token']))->toBe(401)
        ->and(authorizedNames($user))->toBe(['Test Agent']);

    $this->actingAs($user)->get(route('settings'))->assertSeeText('Test Agent');
});

test('revocation cannot be triggered with GET or without a signed-in user', function () {
    $client = namedClient('Agent');

    $this->actingAs(User::factory()->demo()->create())->get('/settings/mcp-clients/'.$client->getKey())->assertStatus(405);
});

test('the revoke control asks for a confirmation and needs no password', function () {
    $user = User::factory()->demo()->create();
    tokenRow($user, namedClient('Confirm Me'));

    $this->actingAs($user)->get(route('settings'))
        ->assertSeeText(['Revoke access for Confirm Me?', 'This client will no longer be able to access this workspace. You can authorize it again later.'])
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertDontSee('name="password"', false);
});
