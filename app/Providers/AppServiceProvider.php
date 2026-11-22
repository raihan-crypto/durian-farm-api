<?php

namespace App\Providers;

use App\Models\AgriculturalProduct;
use App\Models\DurianVariety;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\HarvestRecord;
use App\Models\ProductCategory;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        // 1. Rate limiter for public tree QR token access
        RateLimiter::for('public-tree', function (Request $request) {
            $perMinute = max((int) config('durian.public_tree.rate_limit_per_minute', 30), 1);

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        // 2. Strict rate limiter for authentication login (prevent credential stuffing / brute force)
        RateLimiter::for('auth-login', function (Request $request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        // 3. General API rate limiter for authenticated users / client IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // 4. Rate limiter for mobile sync batch push
        RateLimiter::for('sync-batch', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 5. Rate limiter for photo uploads
        RateLimiter::for('photo-upload', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // 6. Master data cache invalidation listeners
        DurianVariety::saved(fn () => Cache::forget('durian:master:varieties:active'));
        DurianVariety::deleted(fn () => Cache::forget('durian:master:varieties:active'));

        ProductCategory::saved(fn () => Cache::forget('durian:master:categories:active'));
        ProductCategory::deleted(fn () => Cache::forget('durian:master:categories:active'));

        AgriculturalProduct::saved(fn () => Cache::forget('durian:master:products:active'));
        AgriculturalProduct::deleted(fn () => Cache::forget('durian:master:products:active'));

        // 7. Plantation mutation invalidation for dashboard caches
        $invalidateDashboard = function ($model) {
            $user = auth()->user();
            $orgId = $user?->organization_id ?? 'global';
            Cache::put("org:{$orgId}:last_mutation_time", time(), 86400);
            Cache::put('org:global:last_mutation_time', time(), 86400);
        };

        Tree::saved($invalidateDashboard);
        Tree::deleted($invalidateDashboard);
        HarvestRecord::saved($invalidateDashboard);
        HarvestRecord::deleted($invalidateDashboard);
        FertilizationRecord::saved($invalidateDashboard);
        SprayingRecord::saved($invalidateDashboard);
        FloweringRecord::saved($invalidateDashboard);
        FruitDevelopmentRecord::saved($invalidateDashboard);
        TreeMeasurement::saved($invalidateDashboard);
    }
}
