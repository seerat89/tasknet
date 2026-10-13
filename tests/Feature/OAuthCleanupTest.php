<?php

use App\Models\McpDynamicClient;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Apply the Passport lifetime configuration again, as the service provider does when booting.
 */
function applyPassportLifetimes(): void
{
    $provider = new AppServiceProvider(app());
    $method = new ReflectionMethod($provider, 'configurePassport');
    $method->invoke($provider);
}

/**
 * Create an ordinary Passport client that did not come from dynamic registration.
 */
function createUntrackedClient(): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Manual client', ['http://localhost:7777/callback']);
}

/**
 * Pretend a client was registered this many days ago.
 */
function ageClient(Client $client, int $days): void
{
    McpDynamicClient::query()->whereKey($client->getKey())->update(['created_at' => now()->subDays($days)]);
}

function clientExists(Client $client): bool
{
    return Client::query()->whereKey($client->getKey())->exists();
}

function cleanupOAuth(): void
{
    test()->artisan('tasknet:cleanup-oauth')->assertSuccessful();
}

// --- Configuration ------------------------------------------------------------

test('OAuth lifetimes are one day for access tokens and 30 days for refresh tokens', function () {
    expect(config('tasknet.mcp.oauth'))->toMatchArray([
        'access_token_days' => 1,
        'refresh_token_days' => 30,
        'unused_client_days' => 30,
    ]);
});

test('Passport token lifetimes come from the configuration', function () {
    config(['tasknet.mcp.oauth.access_token_days' => 2, 'tasknet.mcp.oauth.refresh_token_days' => 45]);

    try {
        applyPassportLifetimes();

        expect(Passport::tokensExpireIn()->d)->toBe(2)
            ->and(Passport::refreshTokensExpireIn()->d)->toBe(45);
    } finally {
        config(['tasknet.mcp.oauth.access_token_days' => 1, 'tasknet.mcp.oauth.refresh_token_days' => 30]);
        applyPassportLifetimes();
    }
});

test('issued tokens expire after the configured lifetimes', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();

    $tokens = issueOAuthTokens($user);

    $access = Passport::token()->newQuery()->where('user_id', $user->id)->first();
    $refresh = Passport::refreshToken()->newQuery()->where('access_token_id', $access->id)->first();

    expect((int) now()->diffInDays($access->expires_at, true))->toBe(0)
        ->and(now()->addDay()->diffInMinutes($access->expires_at, true))->toBeLessThan(2)
        ->and(now()->addDays(30)->diffInMinutes($refresh->expires_at, true))->toBeLessThan(2)
        ->and($tokens['client_id'])->not->toBeEmpty();
});

test('the purge keeps expired access tokens for the configured refresh lifetime', function () {
    config(['tasknet.mcp.oauth.refresh_token_days' => 10]);
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $kept = issueOAuthTokens($user);
    $dropped = issueOAuthTokens($user, registerTestClient());

    Passport::token()->newQuery()->where('client_id', $kept['client_id'])->update(['expires_at' => now()->subDays(9)]);
    Passport::token()->newQuery()->where('client_id', $dropped['client_id'])->update(['expires_at' => now()->subDays(11)]);

    cleanupOAuth();

    expect(Passport::token()->newQuery()->where('client_id', $kept['client_id'])->exists())->toBeTrue()
        ->and(Passport::token()->newQuery()->where('client_id', $dropped['client_id'])->exists())->toBeFalse();
});

// --- Tracking -----------------------------------------------------------------

test('clients created by dynamic registration are tracked and other clients are not', function () {
    $dynamic = registerTestClient();
    $manual = createUntrackedClient();

    expect(McpDynamicClient::query()->whereKey($dynamic->getKey())->exists())->toBeTrue()
        ->and(McpDynamicClient::query()->whereKey($manual->getKey())->exists())->toBeFalse();
});

test('a rejected registration creates neither a client nor a record', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Bad', 'redirect_uris' => ['https://evil.example/cb']])
        ->assertStatus(400);

    expect(Client::query()->count())->toBe(0)->and(McpDynamicClient::query()->count())->toBe(0);
});

test('deleting a client removes its tracking record', function () {
    $client = registerTestClient();

    $client->delete();

    expect(DB::table('mcp_dynamic_clients')->count())->toBe(0);
});

// --- Cleanup ------------------------------------------------------------------

test('a new unused client is kept during the grace period and deleted after it', function () {
    $client = registerTestClient();

    ageClient($client, 29);
    cleanupOAuth();
    expect(clientExists($client))->toBeTrue();

    ageClient($client, 31);
    cleanupOAuth();
    expect(clientExists($client))->toBeFalse()
        ->and(McpDynamicClient::query()->count())->toBe(0);
});

test('a client with an active authorization is not deleted', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);
    $client = Client::query()->findOrFail($tokens['client_id']);

    ageClient($client, 400);
    cleanupOAuth();

    expect(clientExists($client))->toBeTrue();
});

test('an expired access token with a valid refresh token keeps the client', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);
    $client = Client::query()->findOrFail($tokens['client_id']);

    // Past the access token's expiry, but well inside the refresh token's lifetime.
    $this->travel(5)->days();
    ageClient($client, 400);

    cleanupOAuth();

    expect(clientExists($client))->toBeTrue()
        ->and(Passport::token()->newQuery()->where('client_id', $client->getKey())->exists())->toBeTrue();
});

test('an unexchanged authorization code keeps the client', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $client = registerTestClient();
    requestAuthorizationCode($user, $client, Str::random(64));

    ageClient($client, 400);
    cleanupOAuth();

    expect(clientExists($client))->toBeTrue();
});

test('a shared client survives until every workspace has lost its authorization', function () {
    useTemporaryPassportKeys();
    $a = User::factory()->demo()->create();
    $b = User::factory()->demo()->create();
    $tokensA = issueOAuthTokens($a);
    issueOAuthTokens($b, Client::query()->findOrFail($tokensA['client_id']));
    $client = Client::query()->findOrFail($tokensA['client_id']);
    ageClient($client, 400);

    $this->actingAs($a)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));
    cleanupOAuth();
    expect(clientExists($client))->toBeTrue();

    auth()->guard('web')->forgetUser();
    $this->actingAs($b)->delete(route('settings.clients.destroy', $client->getKey()))->assertRedirect(route('settings'));
    cleanupOAuth();
    expect(clientExists($client))->toBeFalse();
});

test('a client is deleted once its retained tokens have all been purged', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);
    $client = Client::query()->findOrFail($tokens['client_id']);
    ageClient($client, 400);

    // Both tokens expired, and the refresh token expired long enough ago to be purged.
    $this->travel(70)->days();
    cleanupOAuth();

    expect(Passport::token()->newQuery()->where('client_id', $client->getKey())->exists())->toBeFalse()
        ->and(clientExists($client))->toBeFalse();
});

test('untracked Passport clients are never deleted', function () {
    $manual = createUntrackedClient();
    $manual->forceFill(['created_at' => now()->subYears(2)])->save();

    cleanupOAuth();

    expect(clientExists($manual))->toBeTrue();
});

test('running the cleanup repeatedly is safe', function () {
    $stale = registerTestClient();
    $fresh = registerTestClient();
    ageClient($stale, 31);

    cleanupOAuth();
    cleanupOAuth();
    cleanupOAuth();

    expect(clientExists($stale))->toBeFalse()->and(clientExists($fresh))->toBeTrue();
});
