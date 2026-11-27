<?php

namespace App\Mcp\Concerns;

use App\Actions\RecordMcpActivity;
use App\Models\User;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Passport\AccessToken;

/**
 * Records a client's last activity as the server sends a successful result.
 *
 * This is done here rather than in HTTP middleware because a tool that fails still answers with
 * HTTP 200: only the result says whether the operation succeeded.
 *
 * It overrides a protected Laravel MCP method; tests/Feature/McpClientRecordsTest.php fails if an
 * upgrade changes how results are sent.
 */
trait RecordsActivity
{
    protected function send(JsonRpcResponse $response, ServerContext $context, ?JsonRpcRequest $request = null): void
    {
        parent::send($response, $context, $request);

        // Connecting, listing and pinging happen in the background, so only real use counts.
        if (! in_array($request?->method, ['tools/call', 'resources/read'], true)) {
            return;
        }

        $result = $response->toArray()['result'] ?? null;

        if ($result === null || (((array) $result)['isError'] ?? false)) {
            return;
        }

        $user = request()->user('api');
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if ($token instanceof AccessToken) {
            // Keeping this record must never fail the operation that already succeeded.
            rescue(fn () => app(RecordMcpActivity::class)->record($user, $token->oauth_client_id));
        }
    }
}
