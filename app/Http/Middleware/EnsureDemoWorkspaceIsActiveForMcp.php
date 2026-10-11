<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoWorkspaceIsActiveForMcp
{
    /**
     * Reject MCP requests from users whose demo workspace is missing or has expired.
     *
     * A valid OAuth token is not enough: the workspace behind it must still be active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isActiveDemo()) {
            return new JsonResponse([
                'message' => 'This demo workspace has expired. Start a new demo workspace to keep using MCP.',
            ], 403);
        }

        return $next($request);
    }
}
