<?php

namespace NetServa\Core;

use Illuminate\Support\ServiceProvider;
use NetServa\Core\Services\ConfigurationService;
use NetServa\Core\Services\LoggingService;
use NetServa\Core\Services\NotificationService;
use NetServa\Core\Services\RemoteConnectionService;

/**
 * NetServa Core Service Provider
 *
 * Foundation service provider for the NetServa ecosystem.
 * Provides core infrastructure, plugin management, and shared services.
 */
class NetServaCoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services
     */
    public function register(): void
    {
        // Register core singletons
        $this->app->singleton(ConfigurationService::class);
        $this->app->singleton(LoggingService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(RemoteConnectionService::class);

        // Register configuration
        $this->registerConfig();
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrations();

        // Load views
        $this->loadViews();

        // Register commands
        $this->registerCommands();

        // Publish assets
        $this->publishAssets();
    }

    /**
     * Load migrations from the package
     */
    protected function loadMigrations(): void
    {
        $migrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Load views from the package
     */
    protected function loadViews(): void
    {
        $viewsPath = __DIR__.'/../resources/views';
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'netserva-core');
        }
    }

    /**
     * Register console commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            // TODO: Re-enable when commands are implemented
            // $this->commands([
            //     \NetServa\Core\Console\Commands\InstallCommand::class,
            //     \NetServa\Core\Console\Commands\PluginCommand::class,
            // ]);
        }
    }

    /**
     * Register configuration
     */
    protected function registerConfig(): void
    {
        $configPath = __DIR__.'/../config/netserva-core.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'netserva-core');
        }
    }

    /**
     * Publish package assets
     */
    protected function publishAssets(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Publish config files
        $configPath = __DIR__.'/../config/netserva-core.php';
        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path('netserva-core.php'),
            ], 'netserva-core-config');
        }

        // Publish views
        $viewsPath = __DIR__.'/../resources/views';
        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path('views/vendor/netserva-core'),
            ], 'netserva-core-views');
        }

        // Publish migrations
        $migrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($migrationsPath)) {
            $this->publishes([
                $migrationsPath => database_path('migrations'),
            ], 'netserva-core-migrations');
        }
    }
}
