<?php

declare(strict_types=1);

namespace NetServa\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/netserva-admin.php',
            'netserva-admin'
        );
    }

    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-admin');

        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/netserva-admin.php' => config_path('netserva-admin.php'),
            ], 'netserva-admin-config');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/netserva-admin'),
            ], 'netserva-admin-views');
        }
    }
}
