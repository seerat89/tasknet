<?php

namespace App\Http\Controllers;

use App\Models\McpDynamicClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

class RegisterMcpClientController extends OAuthRegisterController
{
    /**
     * Register a client as the package does, and record that it came from dynamic registration.
     *
     * If recording fails the whole registration is rolled back, so a client is never left untracked.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $response = parent::__invoke($request);

            if ($response->getStatusCode() === 201) {
                McpDynamicClient::query()->create([
                    'client_id' => $response->getData(true)['client_id'],
                    'created_at' => now(),
                ]);
            }

            return $response;
        });
    }
}
