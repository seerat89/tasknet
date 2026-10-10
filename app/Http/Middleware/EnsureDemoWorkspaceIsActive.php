<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoWorkspaceIsActive
{
    /**
     * Log out an authenticated demo user whose workspace has expired.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->demoHasExpired()) {
            Auth::guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your demo workspace has expired. Start a new demo to keep testing.');
        }

        return $next($request);
    }
}
