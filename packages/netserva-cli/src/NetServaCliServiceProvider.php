<?php

namespace NetServa\Cli;

use Illuminate\Support\ServiceProvider;
use NetServa\Cli\Commands\NsCommand;
use NetServa\Cli\Console\Commands\AddpwCommand;
use NetServa\Cli\Console\Commands\AddvaliasCommand;
use NetServa\Cli\Console\Commands\AddvconfCommand;
use NetServa\Cli\Console\Commands\AddVhostCommand;
use NetServa\Cli\Console\Commands\AddvmailCommand;
use NetServa\Cli\Console\Commands\BinaryLaneCommand;
use NetServa\Cli\Console\Commands\ChpermsCommand;
use NetServa\Cli\Console\Commands\ChpwCommand;
use NetServa\Cli\Console\Commands\ChvaliasCommand;
use NetServa\Cli\Console\Commands\ChvconfCommand;
use NetServa\Cli\Console\Commands\ChvhostCommand;
use NetServa\Cli\Console\Commands\ChvmailCommand;
use NetServa\Cli\Console\Commands\ClearContextCommand;
use NetServa\Cli\Console\Commands\DelpwCommand;
use NetServa\Cli\Console\Commands\DelvaliasCommand;
use NetServa\Cli\Console\Commands\DelvconfCommand;
use NetServa\Cli\Console\Commands\DelvhostCommand;
use NetServa\Cli\Console\Commands\DelvmailCommand;
use NetServa\Cli\Console\Commands\ImportVmailCredentialsCommand;
use NetServa\Cli\Console\Commands\MigrateCredentialsCommand;
use NetServa\Cli\Console\Commands\MigratePlatformProfilesCommand;
use NetServa\Cli\Console\Commands\MigrateVhostCommand;
use NetServa\Cli\Console\Commands\MigrateVhostConfigsCommand;
use NetServa\Cli\Console\Commands\RollbackVhostCommand;
use NetServa\Cli\Console\Commands\ShhostCommand;
use NetServa\Cli\Console\Commands\ShpwCommand;
use NetServa\Cli\Console\Commands\ShvaliasCommand;
use NetServa\Cli\Console\Commands\ShvconfCommand;
use NetServa\Cli\Console\Commands\ShvhostCommand;
use NetServa\Cli\Console\Commands\ShvmailCommand;
use NetServa\Cli\Console\Commands\TunnelCommand;
use NetServa\Cli\Console\Commands\UserPasswordCommand;
use NetServa\Cli\Console\Commands\UserPasswordShowCommand;
use NetServa\Cli\Console\Commands\UserShowCommand;
use NetServa\Cli\Console\Commands\UseServerCommand;
use NetServa\Cli\Console\Commands\ValidateCommand;
use NetServa\Cli\Services\BinaryLaneService;
use NetServa\Cli\Services\LazyConfigurationCache;
use NetServa\Cli\Services\MigrationExecutionService;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Cli\Services\NetServaContext;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Cli\Services\SshConfigService;
use NetServa\Cli\Services\TunnelService;
use NetServa\Cli\Services\UserManagementService;
use NetServa\Cli\Services\VhostConfigService;
use NetServa\Cli\Services\VhostManagementService;
use NetServa\Cli\Services\VhostRepairService;
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
        $this->app->singleton(TunnelService::class);
        $this->app->singleton(UserManagementService::class);
        $this->app->singleton(VhostManagementService::class);
        $this->app->singleton(VhostValidationService::class);
        $this->app->singleton(VhostRepairService::class);
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
                // Virtual Mail Management (CRUD)
                AddvmailCommand::class,     // CREATE
                ShvmailCommand::class,      // READ
                ChvmailCommand::class,      // UPDATE
                DelvmailCommand::class,     // DELETE
                // Virtual Alias Management (CRUD)
                AddvaliasCommand::class,    // CREATE
                ShvaliasCommand::class,     // READ
                ChvaliasCommand::class,     // UPDATE
                DelvaliasCommand::class,    // DELETE
                // Unified Password/Credential Vault (CRUD - VPass)
                AddpwCommand::class,        // CREATE
                ShpwCommand::class,         // READ
                ChpwCommand::class,         // UPDATE
                DelpwCommand::class,        // DELETE
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
                // Infrastructure Management
                TunnelCommand::class,       // SSH tunnel management
                // VPS Management
                BinaryLaneCommand::class,   // BinaryLane VPS management
                // Monitoring & Analytics
                // Migration Commands
                MigrateCredentialsCommand::class,  // Migrate mail_credentials to VPass
                ImportVmailCredentialsCommand::class, // Import vmails from remote databases
                MigrateVhostConfigsCommand::class, // Migrate vhost configs to database
                MigratePlatformProfilesCommand::class, // Migrate platform profiles to database
                // VHost Configuration Management
                ShvconfCommand::class,      // Show vhost config variables
                AddvconfCommand::class,     // Add/initialize vhost config
                ChvconfCommand::class,      // Change vhost config variable
                DelvconfCommand::class,     // Delete vhost config variables
                // VHost Validation & Migration (Phase 3-4)
                ValidateCommand::class,      // Validate vhost compliance (NetServa CRUD pattern)
                MigrateVhostCommand::class,  // Migrate vhost to NS 3.0
                RollbackVhostCommand::class, // Rollback migrated vhost
            ]);
        }
    }
}
