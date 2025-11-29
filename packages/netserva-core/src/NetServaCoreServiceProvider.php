<?php

namespace NetServa\Core;

use Illuminate\Support\ServiceProvider;
use NetServa\Core\Services\BinaryLaneService;
use NetServa\Core\Services\ConfigurationService;
use NetServa\Core\Services\LazyConfigurationCache;
use NetServa\Core\Services\LoggingService;
use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\NetServaContext;
use NetServa\Core\Services\NotificationService;
use NetServa\Core\Services\RemoteConnectionService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\SshConfigService;
use NetServa\Core\Services\SshHostSyncService;
use NetServa\Core\Services\SshKeySyncService;
use NetServa\Core\Services\SshTunnelService;
use NetServa\Core\Services\TunnelService;
use NetServa\Core\Services\UserManagementService;
use NetServa\Core\Services\VhostConfigService;
use NetServa\Core\Services\VhostRepairService;
use NetServa\Core\Services\VhostValidationService;

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

        // SSH management services
        $this->app->singleton(SshHostSyncService::class);
        $this->app->singleton(SshKeySyncService::class);

        // CLI services (merged from netserva-cli)
        $this->app->singleton(SshConfigService::class);
        $this->app->singleton(TunnelService::class);
        $this->app->singleton(UserManagementService::class);
        $this->app->singleton(VhostValidationService::class);
        $this->app->singleton(VhostRepairService::class);
        $this->app->singleton(MigrationExecutionService::class);
        $this->app->singleton(NetServaConfigurationService::class);
        $this->app->singleton(LazyConfigurationCache::class);
        $this->app->singleton(NetServaContext::class);
        $this->app->singleton(BinaryLaneService::class);

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

            // CLI Commands (merged from netserva-cli)
            \NetServa\Core\Commands\NsCommand::class,
            // Password/Credential Vault CRUD
            \NetServa\Core\Console\Commands\AddpwCommand::class,
            \NetServa\Core\Console\Commands\ShpwCommand::class,
            \NetServa\Core\Console\Commands\ChpwCommand::class,
            \NetServa\Core\Console\Commands\DelpwCommand::class,
            // User Management
            \NetServa\Core\Console\Commands\UserShowCommand::class,
            \NetServa\Core\Console\Commands\UserPasswordCommand::class,
            \NetServa\Core\Console\Commands\UserPasswordShowCommand::class,
            // System Management
            \NetServa\Core\Console\Commands\ShhostCommand::class,
            \NetServa\Core\Console\Commands\ChpermsCommand::class,
            // Context Management
            \NetServa\Core\Console\Commands\UseServerCommand::class,
            \NetServa\Core\Console\Commands\ClearContextCommand::class,
            // Infrastructure Management
            \NetServa\Core\Console\Commands\RemoteExecCommand::class,
            \NetServa\Core\Console\Commands\TunnelCommand::class,
            // VPS Management
            \NetServa\Core\Console\Commands\BinaryLaneCommand::class,
            // Migration Commands
            \NetServa\Core\Console\Commands\MigrateCredentialsCommand::class,
            \NetServa\Core\Console\Commands\ImportVmailCredentialsCommand::class,
            \NetServa\Core\Console\Commands\MigrateVhostConfigsCommand::class,
            \NetServa\Core\Console\Commands\MigratePlatformProfilesCommand::class,
            // VHost Validation & Migration
            \NetServa\Core\Console\Commands\ValidateCommand::class,
            \NetServa\Core\Console\Commands\MigrateVhostCommand::class,
            \NetServa\Core\Console\Commands\RollbackVhostCommand::class,
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
