<?php

use App\Mcp\Servers\TaskNetServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse as McpTestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
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
