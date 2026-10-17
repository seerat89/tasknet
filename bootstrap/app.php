<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureDemoWorkspaceIsActive;
use App\Http\Middleware\ForgetDemoCredentials;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind a TLS-terminating proxy (Vercel) whose addresses are not fixed, so every
        // proxy is trusted and its forwarded scheme, host, port and client headers are honored. This is
        // safe because the container is reachable only through that proxy. Requests without forwarded
        // headers (local Docker) behave as before.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->append(AddSecurityHeaders::class);

        $middleware->web(append: [
            EnsureDemoWorkspaceIsActive::class,
            ForgetDemoCredentials::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp') || $request->expectsJson(),
        );
    })->create();
