<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // NetServa Core foundation services are auto-registered via netserva/core package
        // RemoteConnectionService, ConfigurationService, etc. are available as singletons

        // BaseFilamentPlugin is now provided by NetServa Core package
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Temporary fix for WireGuard view hints until service provider inheritance is resolved
        if (file_exists(base_path('packages/ns-wireguard/resources/views'))) {
            view()->addNamespace('ns-wireguard', base_path('packages/ns-wireguard/resources/views'));
        }
    }
}
