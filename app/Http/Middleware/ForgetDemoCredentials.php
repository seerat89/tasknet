<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForgetDemoCredentials
{
    /**
     * Discard the one-time plaintext credentials as soon as the user leaves the credentials screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('demo.credentials*') && $request->hasSession()) {
            $request->session()->forget('demo_credentials');
        }

        return $next($request);
    }
}
