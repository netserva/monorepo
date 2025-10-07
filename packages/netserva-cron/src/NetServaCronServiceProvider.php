<?php

namespace NetServa\Cron;

use Illuminate\Support\ServiceProvider;
use NetServa\Cron\Services\AutomationService;
use NetServa\Cron\Services\SchedulingService;

/**
 * NetServa Cron Service Provider
 */
class NetServaCronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AutomationService::class);
        $this->app->singleton(SchedulingService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/automation-manager.php', 'automation-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-cron');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/automation-manager.php' => config_path('automation-manager.php'),
            ], 'netserva-cron-config');
        }
    }
}
