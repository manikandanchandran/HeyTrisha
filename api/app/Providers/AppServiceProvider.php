<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WordPressConfigService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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
        // Don't fetch config on boot - it will be lazy-loaded when needed
        // This prevents blocking the application startup
    }
}
