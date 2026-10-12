<?php

use App\Http\Controllers\RegisterMcpClientController;
use App\Http\Middleware\EnsureDemoWorkspaceIsActiveForMcp;
use App\Mcp\Servers\TaskNetServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

// OAuth discovery and dynamic client registration for external MCP clients.
Route::middleware('throttle:mcp-oauth')->group(function () {
    Mcp::oauthRoutes();

    // Replaces the package's registration route (a later route with the same URI wins) so every
    // dynamically registered client is recorded in mcp_dynamic_clients.
    Route::post('oauth/register', RegisterMcpClientController::class);
});

Mcp::web('/mcp', TaskNetServer::class)->middleware([
    'auth:api',
    CheckToken::using('mcp:use'),
    EnsureDemoWorkspaceIsActiveForMcp::class,
    'throttle:mcp',
]);
