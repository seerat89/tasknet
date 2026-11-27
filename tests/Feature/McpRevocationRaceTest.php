<?php

use App\Actions\DeleteDemoWorkspace;
use App\Actions\ListAuthorizedMcpClients;
use App\Actions\RevokeMcpClientAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\AccessTokenRevoked;
use Laravel\Passport\Passport;

// Access removed while a token request is being handled must stay removed.
//
// Passport checks the refresh token or code first and stores the new tokens afterwards, in separate
// statements. Each test removes access at one point between the two, using events raised on the way.
// These run in one process, so they cover the ordering but not database row locks.

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    useTemporaryPassportKeys();
    $this->user = User::factory()->demo()->create();
    $this->tokens = issueOAuthTokens($this->user);
});

/**
 * Run something once, the first time Passport dispatches the event.
 */
function once_during(string $event, Closure $interruption): object
{
    $ran = (object) ['times' => 0];

    Event::listen($event, function () use ($interruption, $ran) {
        if ($ran->times++ === 0) {
            $interruption();
        }
    });

    return $ran;
}

function postRefresh(array $tokens): TestResponse
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $tokens['client_id'],
    ]);
}

function mcpStatusFor(string $accessToken): int
{
    auth()->forgetGuards();
    $status = mcpRequest(test()->withToken($accessToken), 'ping')->getStatusCode();
    test()->withoutHeader('Authorization');

    return $status;
}

/**
 * Nothing may be left that grants access, including tokens the client was never given.
 */
function expectNoAccessLeft(User $user): void
{
    $accessTokens = Passport::token()->newQuery()->where('user_id', $user->id);

    expect((clone $accessTokens)->count())->toBe(0)
        ->and(Passport::refreshToken()->newQuery()->where('revoked', false)->whereNotIn('access_token_id', Passport::token()->newQuery()->select('id'))->count())->toBe(0)
        ->and(app(ListAuthorizedMcpClients::class)->details($user))->toBeEmpty();
}

/** The event Eloquent raises just before an access token row is inserted. */
function beforeAccessTokenIsStored(): string
{
    return 'eloquent.creating: '.Passport::tokenModel();
}

// The moments at which a revocation can slip between Passport's check and its writes.
dataset('moments during a refresh', [
    'after the old token was checked' => [fn () => AccessTokenRevoked::class],
    'before the new access token is stored' => [fn () => beforeAccessTokenIsStored()],
    'between the new access token and the new refresh token' => [fn () => AccessTokenCreated::class],
]);

dataset('moments during a code exchange', [
    'before the new access token is stored' => [fn () => beforeAccessTokenIsStored()],
    'between the new access token and the new refresh token' => [fn () => AccessTokenCreated::class],
]);

test('a refresh overtaken by revocation gets no tokens', function (string $moment) {
    $hook = once_during($moment, fn () => app(RevokeMcpClientAccess::class)->revoke($this->user, $this->tokens['client_id']));

    $response = postRefresh($this->tokens);

    expect($hook->times)->toBeGreaterThan(0)
        ->and($response->getStatusCode())->toBe(400)
        ->and($response->json('error'))->toBe('invalid_grant')
        ->and($response->json())->not->toHaveKeys(['access_token', 'refresh_token']);

    expectNoAccessLeft($this->user);
    expect(mcpStatusFor($this->tokens['access_token']))->toBe(401)
        ->and(postRefresh($this->tokens)->getStatusCode())->toBeGreaterThanOrEqual(400);
})->with('moments during a refresh');

test('a refresh overtaken by workspace deletion gets no tokens', function (string $moment) {
    $hook = once_during($moment, fn () => app(DeleteDemoWorkspace::class)->delete($this->user));

    $response = postRefresh($this->tokens);

    expect($hook->times)->toBeGreaterThan(0)->and($response->getStatusCode())->toBe(400);
    expectNoAccessLeft($this->user);
})->with('moments during a refresh');

test('a code exchange overtaken by revocation gets no tokens', function (string $moment) {
    $client = Passport::client()->newQuery()->findOrFail($this->tokens['client_id']);
    $this->travel(2)->days(); // The first access token has lapsed, so approval is asked again.
    $verifier = Str::random(64);
    $code = requestAuthorizationCode($this->user, $client, $verifier);
    $hook = once_during($moment, fn () => app(RevokeMcpClientAccess::class)->revoke($this->user, $client->getKey()));

    $response = exchangeAuthorizationCode($client, $code, $verifier);

    expect($hook->times)->toBeGreaterThan(0)
        ->and($response->getStatusCode())->toBe(400)
        ->and($response->json('error'))->toBe('invalid_grant')
        ->and($response->json())->not->toHaveKeys(['access_token', 'refresh_token']);

    expectNoAccessLeft($this->user);
    expect(exchangeAuthorizationCode($client, $code, $verifier)->getStatusCode())->toBeGreaterThanOrEqual(400);
})->with('moments during a code exchange');

test('revoking works while a refresh has left no token live', function () {
    // The state between Passport retiring the old tokens and storing the new ones.
    Passport::token()->newQuery()->where('user_id', $this->user->id)->update(['revoked' => true]);
    Passport::refreshToken()->newQuery()->update(['revoked' => true]);
    expect(app(ListAuthorizedMcpClients::class)->details($this->user))->toBeEmpty();

    $this->actingAs($this->user)->delete(route('settings.clients.destroy', $this->tokens['client_id']))
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Access revoked for Test Agent.');

    expectNoAccessLeft($this->user);
});

test('another workspace revoking a shared client does not disturb a refresh', function (string $moment) {
    $other = User::factory()->demo()->create();
    $client = Passport::client()->newQuery()->findOrFail($this->tokens['client_id']);
    issueOAuthTokens($other, $client);
    once_during($moment, fn () => app(RevokeMcpClientAccess::class)->revoke($other, $client->getKey()));

    $response = postRefresh($this->tokens)->assertOk();

    expect(mcpStatusFor($response->json('access_token')))->toBe(200)
        ->and(app(ListAuthorizedMcpClients::class)->details($other))->toBeEmpty();
})->with('moments during a refresh');

test('refreshing repeatedly still works, and revocation then ends it', function () {
    $tokens = $this->tokens;

    foreach (range(1, 3) as $ignored) {
        $response = postRefresh($tokens)->assertOk();
        $tokens = [...$tokens, 'access_token' => $response->json('access_token'), 'refresh_token' => $response->json('refresh_token')];
    }

    expect(mcpStatusFor($tokens['access_token']))->toBe(200);

    app(RevokeMcpClientAccess::class)->revoke($this->user, $tokens['client_id']);

    expect(mcpStatusFor($tokens['access_token']))->toBe(401)
        ->and(postRefresh($tokens)->getStatusCode())->toBeGreaterThanOrEqual(400);
    expectNoAccessLeft($this->user);
});

// A rejected refresh must leave nothing behind that could affect the next token request.
test('a new authorization works straight after an overtaken refresh', function () {
    once_during(AccessTokenRevoked::class, fn () => app(RevokeMcpClientAccess::class)->revoke($this->user, $this->tokens['client_id']));
    postRefresh($this->tokens)->assertStatus(400);

    $client = Passport::client()->newQuery()->findOrFail($this->tokens['client_id']);
    $fresh = issueOAuthTokens($this->user, $client);

    expect(mcpStatusFor($fresh['access_token']))->toBe(200)
        ->and(postRefresh($fresh)->getStatusCode())->toBe(200);
});
