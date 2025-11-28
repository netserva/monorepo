<?php

namespace NetServa\Ops;

use Illuminate\Support\ServiceProvider;
use NetServa\Ops\Services\AnalyticsService;
use NetServa\Ops\Services\AutomationService;
use NetServa\Ops\Services\BackupService;
use NetServa\Ops\Services\MonitoringService;

/**
 * NetServa Operations Service Provider
 */
class NetServaOpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupService::class);
        $this->app->singleton(MonitoringService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(AutomationService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/ops-manager.php', 'ops-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-ops');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/ops-manager.php' => config_path('ops-manager.php'),
            ], 'netserva-ops-config');
        }
    }
}
