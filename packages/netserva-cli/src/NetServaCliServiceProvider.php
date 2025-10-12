<?php

namespace NetServa\Cli;

use Illuminate\Support\ServiceProvider;
use NetServa\Cli\Commands\NsCommand;
use NetServa\Cli\Console\Commands\AddvconfCommand;
use NetServa\Cli\Console\Commands\AddVhostCommand;
use NetServa\Cli\Console\Commands\AddvmailCommand;
use NetServa\Cli\Console\Commands\BinaryLaneCommand;
use NetServa\Cli\Console\Commands\ChpermsCommand;
use NetServa\Cli\Console\Commands\ChpwCommand;
use NetServa\Cli\Console\Commands\ChvconfCommand;
use NetServa\Cli\Console\Commands\ChvhostCommand;
use NetServa\Cli\Console\Commands\ClearContextCommand;
use NetServa\Cli\Console\Commands\DelvconfCommand;
use NetServa\Cli\Console\Commands\DelvhostCommand;
use NetServa\Cli\Console\Commands\MigratePlatformProfilesCommand;
use NetServa\Cli\Console\Commands\MigrateVhostConfigsCommand;
use NetServa\Cli\Console\Commands\ShhostCommand;
use NetServa\Cli\Console\Commands\ShpwCommand;
use NetServa\Cli\Console\Commands\ShvconfCommand;
use NetServa\Cli\Console\Commands\ShvhostCommand;
use NetServa\Cli\Console\Commands\UserPasswordCommand;
use NetServa\Cli\Console\Commands\UserPasswordShowCommand;
use NetServa\Cli\Console\Commands\UserShowCommand;
use NetServa\Cli\Console\Commands\UseServerCommand;
use NetServa\Cli\Console\Commands\ValidateVhostCommand;
use NetServa\Cli\Console\Commands\MigrateVhostCommand;
use NetServa\Cli\Console\Commands\RollbackVhostCommand;
use NetServa\Cli\Services\BinaryLaneService;
use NetServa\Cli\Services\LazyConfigurationCache;
use NetServa\Cli\Services\MigrationExecutionService;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Cli\Services\NetServaContext;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Cli\Services\SshConfigService;
use NetServa\Cli\Services\UserManagementService;
use NetServa\Cli\Services\VhostConfigService;
use NetServa\Cli\Services\VhostManagementService;
use NetServa\Cli\Services\VhostValidationService;
use NetServa\Cli\Services\VmailManagementService;

/**
 * NetServa CLI Service Provider
 *
 * Provides unified command-line interfaces for NetServa infrastructure management.
 * Works standalone without requiring other NetServa packages.
 */
class NetServaCliServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/netserva-cli.php',
            'netserva-cli'
        );

        // Register core services as singletons
        $this->app->singleton(VhostConfigService::class);
        $this->app->singleton(SshConfigService::class);
        $this->app->singleton(RemoteExecutionService::class);
        $this->app->singleton(UserManagementService::class);
        $this->app->singleton(VhostManagementService::class);
        $this->app->singleton(VhostValidationService::class);
        $this->app->singleton(MigrationExecutionService::class);
        $this->app->singleton(VmailManagementService::class);
        $this->app->singleton(NetServaConfigurationService::class);
        $this->app->singleton(LazyConfigurationCache::class);
        $this->app->singleton(NetServaContext::class);
        $this->app->singleton(BinaryLaneService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/netserva-cli.php' => config_path('netserva-cli.php'),
        ], 'netserva-cli-config');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                NsCommand::class,
                // NetServa CRUD Pattern Commands
                AddVhostCommand::class,     // CREATE
                ShvhostCommand::class,      // READ
                ChvhostCommand::class,      // UPDATE
                DelvhostCommand::class,     // DELETE
                // Virtual Mail Management
                AddvmailCommand::class,     // CREATE
                // Password Management
                ShpwCommand::class,         // Show passwords
                ChpwCommand::class,         // Change passwords
                // User Management
                UserShowCommand::class,     // Show user information
                UserPasswordCommand::class, // Change user password
                UserPasswordShowCommand::class, // Show user password
                // System Management
                ShhostCommand::class,       // Show system users
                ChpermsCommand::class,      // Fix permissions
                // Context Management
                UseServerCommand::class,    // Set server context
                ClearContextCommand::class, // Clear context
                // VPS Management
                BinaryLaneCommand::class,   // BinaryLane VPS management
                // Monitoring & Analytics
                // Migration Commands
                MigrateVhostConfigsCommand::class, // Migrate vhost configs to database
                MigratePlatformProfilesCommand::class, // Migrate platform profiles to database
                // VHost Configuration Management
                ShvconfCommand::class,      // Show vhost config variables
                AddvconfCommand::class,     // Add/initialize vhost config
                ChvconfCommand::class,      // Change vhost config variable
                DelvconfCommand::class,     // Delete vhost config variables
                // VHost Validation & Migration (Phase 3-4)
                ValidateVhostCommand::class, // Validate vhost compliance
                MigrateVhostCommand::class,  // Migrate vhost to NS 3.0
                RollbackVhostCommand::class, // Rollback migrated vhost
            ]);
        }
    }
}
