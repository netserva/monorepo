<?php

namespace NetServa\Ipam;

use Illuminate\Support\ServiceProvider;
use NetServa\Ipam\Services\IpAddressService;
use NetServa\Ipam\Services\NetworkService;

/**
 * NetServa IPAM Service Provider
 */
class NetServaIpamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IpAddressService::class);
        $this->app->singleton(NetworkService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/ipam-manager.php', 'ipam-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-ipam');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/ipam-manager.php' => config_path('ipam-manager.php'),
            ], 'netserva-ipam-config');
        }
    }
}
