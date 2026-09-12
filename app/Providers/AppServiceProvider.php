<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Every tenant-facing API token, keyed by the token itself so one
        // customer's traffic can never eat into another's allowance.
        RateLimiter::for('api-token', function (Request $request) {
            return Limit::perMinute(config('portal.api_rate_limit'))
                ->by($request->bearerToken() ?: $request->ip())
                ->response(fn () => response()->json([
                    'status' => 'error',
                    'message' => 'Rate limit reached. Slow down and retry.',
                ], 429));
        });

        // Login/register attempts, matching the reference app's guard
        // against credential stuffing.
        RateLimiter::for('auth-attempts', function (Request $request) {
            return Limit::perMinutes(10, 12)->by($request->ip())
                ->response(fn () => response()->json([
                    'status' => 'error',
                    'message' => 'Too many attempts. Try again in a few minutes.',
                ], 429));
        });
    }
}
