<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;

uses(LazilyRefreshDatabase::class);

const CHATGPT_CALLBACK = 'https://chatgpt.com/connector/oauth/Fe0Jr2Iw7yd0';

/**
 * Register with the payload ChatGPT sends to the registration endpoint.
 *
 * @param  array<int, string>  $redirectUris
 */
function registerChatGpt(array $redirectUris = [CHATGPT_CALLBACK]): TestResponse
{
    return test()->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => $redirectUris,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'client_secret_basic',
    ]);
}

function chatGptAuthorizeUrl(string $clientId, string $redirectUri, string $verifier): string
{
    return route('passport.authorizations.authorize', [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);
}

test('documented ChatGPT callbacks can be registered', function (string $uri) {
    registerChatGpt([$uri])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', [$uri])
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonMissingPath('client_secret');
})->with([
    'callback id' => CHATGPT_CALLBACK,
    'callback id with separators' => 'https://chatgpt.com/connector/oauth/abc_DEF-123',
    'stable redirect' => 'https://chatgpt.com/connector_platform_oauth_redirect',
]);

dataset('untrusted ChatGPT callbacks', [
    'plain http' => 'http://chatgpt.com/connector/oauth/abc',
    'suffixed host' => 'https://chatgpt.com.evil.example/connector/oauth/abc',
    'prefixed host' => 'https://evilchatgpt.com/connector/oauth/abc',
    'subdomain' => 'https://sub.chatgpt.com/connector/oauth/abc',
    'trailing dot host' => 'https://chatgpt.com./connector/oauth/abc',
    'uppercase host' => 'https://CHATGPT.COM/connector/oauth/abc',
    'uppercase scheme' => 'HTTPS://chatgpt.com/connector/oauth/abc',
    'explicit port' => 'https://chatgpt.com:8443/connector/oauth/abc',
    'userinfo' => 'https://user@chatgpt.com/connector/oauth/abc',
    'trusted host as userinfo' => 'https://chatgpt.com@evil.example/connector/oauth/abc',
    'backslash before host' => 'https://evil.example\@chatgpt.com/connector/oauth/abc',
    'site root' => 'https://chatgpt.com/',
    'other path' => 'https://chatgpt.com/share/abc',
    'missing callback id' => 'https://chatgpt.com/connector/oauth/',
    'trailing slash' => 'https://chatgpt.com/connector/oauth/abc/',
    'extra segment' => 'https://chatgpt.com/connector/oauth/abc/extra',
    'dot segments' => 'https://chatgpt.com/connector/oauth/../../share/abc',
    'encoded slash' => 'https://chatgpt.com/connector/oauth/..%2F..%2Fshare',
    'query string' => 'https://chatgpt.com/connector/oauth/abc?next=https://evil.example',
    'fragment' => 'https://chatgpt.com/connector/oauth/abc#fragment',
    'embedded newline' => "https://chatgpt.com/connector/oauth/abc\nevil",
    'suffixed stable redirect' => 'https://chatgpt.com/connector_platform_oauth_redirect_evil',
    'path under stable redirect' => 'https://chatgpt.com/connector_platform_oauth_redirect/evil',
    'query on stable redirect' => 'https://chatgpt.com/connector_platform_oauth_redirect?next=evil',
]);

test('untrusted ChatGPT look-alike callbacks are rejected', function (string $uri) {
    registerChatGpt([$uri])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    expect(Client::query()->count())->toBe(0);
})->with('untrusted ChatGPT callbacks');

test('one untrusted URI rejects the whole registration', function (string $uri) {
    registerChatGpt([CHATGPT_CALLBACK, $uri])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    expect(Client::query()->count())->toBe(0);
})->with([
    'other domain' => 'https://evil.example/callback',
    'query added to a valid callback' => CHATGPT_CALLBACK.'?next=evil',
    'dot segments after a valid callback' => CHATGPT_CALLBACK.'/../../share',
]);

test('configuring the whole ChatGPT origin does not widen the permitted paths', function () {
    config()->set('mcp.redirect_domains', ['https://chatgpt.com']);

    registerChatGpt(['https://chatgpt.com/share/abc'])->assertStatus(400);
    registerChatGpt()->assertCreated();
});

test('a Claude callback on a configured domain is still accepted', function () {
    config()->set('mcp.redirect_domains', ['https://claude.ai']);

    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->assertJsonPath('redirect_uris.0', 'https://claude.ai/api/mcp/auth_callback');
});

test('authorization requires the exact registered ChatGPT callback', function (string $uri) {
    $clientId = registerChatGpt()->assertCreated()->json('client_id');

    $response = $this->actingAs(User::factory()->demo()->create())
        ->get(chatGptAuthorizeUrl($clientId, $uri, Str::random(64)));

    $response->assertUnauthorized()->assertHeaderMissing('Location');
})->with([
    'another callback id' => 'https://chatgpt.com/connector/oauth/AnotherId123',
    'stable redirect' => 'https://chatgpt.com/connector_platform_oauth_redirect',
    'added query' => CHATGPT_CALLBACK.'?next=evil',
    'trailing slash' => CHATGPT_CALLBACK.'/',
    'other domain' => 'https://evil.example/callback',
]);

// ChatGPT asks for client_secret_basic but is registered as a public client, so a Basic header
// carrying only the client ID has to work. Any secret in it is ignored; PKCE protects the code.
test('ChatGPT can exchange and refresh tokens with HTTP Basic client authentication', function (string $secret) {
    useTemporaryPassportKeys();

    $user = User::factory()->demo()->create();
    $clientId = registerChatGpt()->assertCreated()->json('client_id');
    $verifier = Str::random(64);

    $screen = $this->actingAs($user)->get(chatGptAuthorizeUrl($clientId, CHATGPT_CALLBACK, $verifier))->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $screen->getContent(), $matches);

    $location = $this->post(route('passport.authorizations.approve'), [
        'state' => 'xyz',
        'client_id' => $clientId,
        'auth_token' => $matches[1],
    ])->headers->get('Location');

    expect($location)->toStartWith(CHATGPT_CALLBACK.'?');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);

    auth()->guard('web')->forgetUser();
    $this->flushSession();

    $basic = ['Authorization' => 'Basic '.base64_encode("{$clientId}:{$secret}")];

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'redirect_uri' => CHATGPT_CALLBACK,
        'code' => $query['code'],
        'code_verifier' => $verifier,
    ], $basic)->assertOk()->json();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $token['refresh_token'],
    ], $basic)->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
})->with([
    'empty secret' => '',
    'unused secret' => 'ignored-secret',
]);
