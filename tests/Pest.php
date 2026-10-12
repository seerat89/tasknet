<?php

use App\Mcp\Servers\TaskNetServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse as McpTestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->afterEach(function () {
    Passport::$keyPath = null;

    if (isset($this->keyPath)) {
        File::deleteDirectory($this->keyPath);
    }
})->in('Feature');

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

/**
 * Sign the user in, approve the consent screen for a client and return the authorization code.
 */
function requestAuthorizationCode(User $user, Client $client, string $verifier): string
{
    $screen = test()->actingAs($user)->get(authorizeUrl($client, $verifier));

    // Passport skips the consent screen for a client the user has already authorized.
    if ($screen->isRedirect()) {
        parse_str(parse_url($screen->headers->get('Location'), PHP_URL_QUERY), $query);
    } else {
        $screen->assertOk();
        preg_match('/name="auth_token" value="([^"]+)"/', $screen->getContent(), $matches);

        $approval = test()->post(route('passport.authorizations.approve'), [
            'state' => 'xyz',
            'client_id' => $client->getKey(),
            'auth_token' => $matches[1],
        ]);

        parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $query);
    }

    // Passport's controller keeps its guard instance, so clear the user rather than replacing the guard.
    auth()->guard('web')->forgetUser();
    test()->flushSession();

    return $query['code'];
}

/**
 * Exchange an authorization code for tokens at the token endpoint.
 */
function exchangeAuthorizationCode(Client $client, string $code, string $verifier): TestResponse
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:7777/callback',
        'code' => $code,
        'code_verifier' => $verifier,
    ]);
}

/**
 * Run the real OAuth authorization-code flow for a user and return the issued access and refresh tokens.
 *
 * Pass an existing client to authorize it again, or to let another workspace authorize the same client.
 *
 * @return array{access_token: string, refresh_token: string, client_id: string}
 */
function issueOAuthTokens(User $user, ?Client $client = null): array
{
    $client ??= registerTestClient();
    $verifier = Str::random(64);

    $code = requestAuthorizationCode($user, $client, $verifier);
    $token = exchangeAuthorizationCode($client, $code, $verifier)->assertOk()->json();

    return ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'client_id' => (string) $client->getKey()];
}

/**
 * Send a JSON-RPC request to the MCP endpoint over HTTP.
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
 * Start an in-process MCP test session as the given OAuth user.
 */
function mcpAs(User $user): PendingTestResponse
{
    return TaskNetServer::actingAs($user, 'api');
}

/**
 * Call a TaskNet MCP tool in-process as the given OAuth user.
 *
 * Mutation tools are not advertised directly; they live in the searchable tool catalog,
 * so they are run through execute_tools exactly as an AI client would run them.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function callMcpTool(User $user, string $tool, array $arguments = []): McpTestResponse
{
    $catalogTools = [CreateProjectTool::class, UpdateProjectTool::class, CreateTaskTool::class, UpdateTaskTool::class];

    if (! in_array($tool, $catalogTools, true)) {
        return mcpAs($user)->tool($tool, $arguments);
    }

    [, $executeTools] = (new ToolSearch($catalogTools))->tools();

    return mcpAs($user)->tool($executeTools, [
        'calls' => [['name' => (new $tool)->name(), 'arguments' => $arguments]],
    ]);
}

/**
 * Run one catalog tool over HTTP through execute_tools.
 *
 * @param  array<string, mixed>  $arguments
 */
function executeCatalogTool(mixed $test, string $tool, array $arguments = []): TestResponse
{
    return mcpRequest($test, 'tools/call', [
        'name' => 'execute_tools',
        'arguments' => ['calls' => [['name' => $tool, 'arguments' => (object) $arguments]]],
    ]);
}

/**
 * Decode the JSON-RPC result of a streamed (server-sent events) MCP response.
 *
 * @return array<string, mixed>
 */
function mcpResult(TestResponse $response): array
{
    $payload = $response->baseResponse instanceof StreamedResponse
        ? trim(Str::after($response->streamedContent(), 'data: '))
        : $response->getContent();

    return json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['result'];
}
