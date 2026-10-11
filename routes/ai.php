<?php

use App\Http\Middleware\EnsureDemoWorkspaceIsActiveForMcp;
use App\Mcp\Servers\TaskNetServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

// OAuth discovery and dynamic client registration for external MCP clients.
Route::middleware('throttle:mcp-oauth')->group(function () {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp', TaskNetServer::class)->middleware([
    'auth:api',
    CheckToken::using('mcp:use'),
    EnsureDemoWorkspaceIsActiveForMcp::class,
]);
