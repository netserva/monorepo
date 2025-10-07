<?php

namespace NetServa\Web;

use Illuminate\Support\ServiceProvider;
use NetServa\Web\Services\NginxService;
use NetServa\Web\Services\SslCertificateService;
use NetServa\Web\Services\WebServerService;

/**
 * NetServa Web Service Provider
 */
class NetServaWebServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebServerService::class);
        $this->app->singleton(SslCertificateService::class);
        $this->app->singleton(NginxService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/web-manager.php', 'web-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-web');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/web-manager.php' => config_path('web-manager.php'),
            ], 'netserva-web-config');
        }
    }
}
