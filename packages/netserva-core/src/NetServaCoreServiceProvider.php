<?php

namespace NetServa\Core;

use Illuminate\Support\ServiceProvider;
use NetServa\Core\Services\ConfigurationService;
use NetServa\Core\Services\LoggingService;
use NetServa\Core\Services\NotificationService;
use NetServa\Core\Services\RemoteConnectionService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\SshTunnelService;
use NetServa\Core\Services\VhostConfigService;

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
        $this->app->singleton(SshTunnelService::class);
        $this->app->singleton(VhostConfigService::class);
        $this->app->singleton(RemoteExecutionService::class);

        // Register configuration
        $this->registerConfig();
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Override app.name from CMS settings if available (progressive enhancement)
        $this->overrideAppNameFromSettings();

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
     * Override config('app.name') from cms.name setting if it exists
     *
     * Progressive enhancement: Uses database setting if CMS is installed,
     * otherwise falls back to .env APP_NAME
     */
    protected function overrideAppNameFromSettings(): void
    {
        try {
            // Skip if running migrations or database not ready
            if ($this->app->runningInConsole() &&
                (in_array('migrate', $_SERVER['argv'] ?? []) ||
                 in_array('migrate:fresh', $_SERVER['argv'] ?? []))) {
                return;
            }

            // Check if Setting model exists (CMS might not be installed)
            if (! class_exists(\NetServa\Core\Models\Setting::class)) {
                return;
            }

            // Check if settings table exists
            if (! \Illuminate\Support\Facades\Schema::hasTable('netserva_settings')) {
                return;
            }

            // Get app.name setting
            $appName = \NetServa\Core\Models\Setting::getValue('app.name');

            // Override config if setting exists and is not empty
            if (! empty($appName) && is_string($appName)) {
                config(['app.name' => $appName]);
            }
        } catch (\Exception $e) {
            // Silently fail - setting override is optional
            // Log only in debug mode
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::debug(
                    'Could not override app.name from settings: '.$e->getMessage()
                );
            }
        }
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
     *
     * Note: Commands are registered unconditionally to support Artisan::call()
     * from web context (e.g., Filament actions)
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \NetServa\Core\Console\Commands\ImportSshHostsCommand::class,

            // SSH Host CRUD Commands (NetServa 3.0)
            \NetServa\Core\Console\Commands\AddsshCommand::class,   // CREATE
            \NetServa\Core\Console\Commands\ShsshCommand::class,    // READ
            \NetServa\Core\Console\Commands\ChsshCommand::class,    // UPDATE
            \NetServa\Core\Console\Commands\DelsshCommand::class,   // DELETE

            // Settings CRUD Commands
            \NetServa\Core\Console\Commands\AddcfgCommand::class,   // CREATE
            \NetServa\Core\Console\Commands\ShcfgCommand::class,    // READ
            \NetServa\Core\Console\Commands\ChcfgCommand::class,    // UPDATE
            \NetServa\Core\Console\Commands\DelcfgCommand::class,   // DELETE

            // Install & Plugin Management
            \NetServa\Core\Console\Commands\InstallCommand::class,
            \NetServa\Core\Console\Commands\PluginCommand::class,
            \NetServa\Core\Console\Commands\PluginDiscoverCommand::class,
            \NetServa\Core\Console\Commands\PluginEnableCommand::class,
            \NetServa\Core\Console\Commands\PluginDisableCommand::class,
            \NetServa\Core\Console\Commands\PluginInfoCommand::class,
            \NetServa\Core\Console\Commands\PluginListCommand::class,
        ]);
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
