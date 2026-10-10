<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView('auth.login');

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('demo_id', $this->normalizedDemoId($request))
                ->where('is_demo', true)
                ->first();

            $password = $request->string('password')->toString();

            if ($user === null) {
                // Spend comparable time as a real check so unknown Demo IDs are not detectable by timing.
                Hash::make($password);

                return null;
            }

            if (! Hash::check($password, $user->password) || $user->demoHasExpired()) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate($this->normalizedDemoId($request).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Normalize the submitted Demo ID so it can be typed in any case.
     */
    private function normalizedDemoId(Request $request): string
    {
        return $request->string(Fortify::username())->trim()->upper()->toString();
    }
}
