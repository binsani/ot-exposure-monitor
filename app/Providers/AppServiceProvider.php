<?php

namespace App\Providers;

use App\Models\Asset;
use App\Observers\AssetObserver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Vite;
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
        Asset::observe(AssetObserver::class);
        Vite::prefetch(concurrency: 3);
    }
}
