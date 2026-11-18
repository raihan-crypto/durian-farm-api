<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        RateLimiter::for('public-tree', function (Request $request) {
            $perMinute = max((int) config('durian.public_tree.rate_limit_per_minute', 30), 1);

            return Limit::perMinute($perMinute)->by($request->ip());
        });
    }
}
