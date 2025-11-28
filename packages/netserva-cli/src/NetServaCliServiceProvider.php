<?php

namespace NetServa\Cli;

use Illuminate\Support\ServiceProvider;
use NetServa\Cli\Commands\NsCommand;
use NetServa\Cli\Console\Commands\AddpwCommand;
use NetServa\Cli\Console\Commands\BinaryLaneCommand;
use NetServa\Cli\Console\Commands\ChpermsCommand;
use NetServa\Cli\Console\Commands\ChpwCommand;
use NetServa\Cli\Console\Commands\ClearContextCommand;
use NetServa\Cli\Console\Commands\DelpwCommand;
use NetServa\Cli\Console\Commands\ImportVmailCredentialsCommand;
use NetServa\Cli\Console\Commands\MigrateCredentialsCommand;
use NetServa\Cli\Console\Commands\MigratePlatformProfilesCommand;
use NetServa\Cli\Console\Commands\MigrateVhostCommand;
use NetServa\Cli\Console\Commands\MigrateVhostConfigsCommand;
use NetServa\Cli\Console\Commands\RemoteExecCommand;
use NetServa\Cli\Console\Commands\RollbackVhostCommand;
use NetServa\Cli\Console\Commands\ShhostCommand;
use NetServa\Cli\Console\Commands\ShpwCommand;
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
use NetServa\Cli\Services\SshConfigService;
use NetServa\Cli\Services\TunnelService;
use NetServa\Cli\Services\UserManagementService;
use NetServa\Cli\Services\VhostRepairService;
use NetServa\Cli\Services\VhostValidationService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\VhostConfigService;

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
        // VhostManagementService moved to NetServa\Fleet package
        $this->app->singleton(VhostValidationService::class);
        $this->app->singleton(VhostRepairService::class);
        $this->app->singleton(MigrationExecutionService::class);
        // VmailManagementService moved to NetServa\Mail package
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
                // VHost/VConf CRUD commands moved to NetServa\Fleet package:
                // addvhost, shvhost, chvhost, delvhost
                // addvconf, shvconf, chvconf, delvconf
                // Virtual Mail commands moved to NetServa\Mail package:
                // addvmail, shvmail, chvmail, delvmail
                // addvalias, shvalias, chvalias, delvalias
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
                RemoteExecCommand::class,   // Execute scripts on remote vnodes
                TunnelCommand::class,       // SSH tunnel management
                // VPS Management
                BinaryLaneCommand::class,   // BinaryLane VPS management
                // Migration Commands
                MigrateCredentialsCommand::class,  // Migrate mail_credentials to VPass
                ImportVmailCredentialsCommand::class, // Import vmails from remote databases
                MigrateVhostConfigsCommand::class, // Migrate vhost configs to database
                MigratePlatformProfilesCommand::class, // Migrate platform profiles to database
                // VHost Validation & Migration (Phase 3-4)
                ValidateCommand::class,      // Validate vhost compliance (NetServa CRUD pattern)
                MigrateVhostCommand::class,  // Migrate vhost to NS 3.0
                RollbackVhostCommand::class, // Rollback migrated vhost
            ]);
        }
    }
}
