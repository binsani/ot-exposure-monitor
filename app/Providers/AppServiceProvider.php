<?php

namespace App\Providers;

use App\Models\Asset;
use App\Observers\AssetObserver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('agent', fn (Request $request) => Limit::perMinute(60)->by(hash('sha256', (string) $request->bearerToken()).'|'.$request->ip()));
        Asset::observe(AssetObserver::class);
        Vite::prefetch(concurrency: 3);
    }
}
