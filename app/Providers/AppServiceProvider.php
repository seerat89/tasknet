<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Must be set before Passport boots and registers its routes and grants.
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('demo-workspaces', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('mcp-oauth', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('mcp', function (Request $request) {
            $workspace = $request->user('api');

            return Limit::perMinute(config('tasknet.mcp.requests_per_minute'))
                ->by($workspace ? 'workspace:'.$workspace->getAuthIdentifier() : $request->ip());
        });

        $this->configurePassport();
    }

    /**
     * Configure Passport as the OAuth provider for external MCP clients.
     *
     * Only the authorization code flow (with PKCE) and refresh tokens are available.
     */
    private function configurePassport(): void
    {
        Passport::authorizationView('oauth.authorize');

        Passport::tokensCan([
            'mcp:use' => 'Read and manage your TaskNet projects and tasks through MCP',
        ]);
        Passport::defaultScopes(['mcp:use']);

        Passport::tokensExpireIn(now()->addDay());
        Passport::refreshTokensExpireIn(now()->addDays(config('tasknet.demo.lifetime_days')));
    }
}
