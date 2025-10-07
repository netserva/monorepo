<?php

namespace NetServa\Wg;

use Illuminate\Support\ServiceProvider;
use NetServa\Wg\Services\PeerManagementService;
use NetServa\Wg\Services\WireguardService;

/**
 * NetServa WireGuard Service Provider
 */
class NetServaWgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WireguardService::class);
        $this->app->singleton(PeerManagementService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/wireguard-manager.php', 'wireguard-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-wg');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/wireguard-manager.php' => config_path('wireguard-manager.php'),
            ], 'netserva-wg-config');
        }
    }
}
