<?php

namespace NetServa\Mail;

use Illuminate\Support\ServiceProvider;
use NetServa\Mail\Console\Commands\AddvaliasCommand;
use NetServa\Mail\Console\Commands\AddvmailCommand;
use NetServa\Mail\Console\Commands\ChvaliasCommand;
use NetServa\Mail\Console\Commands\ChvmailCommand;
use NetServa\Mail\Console\Commands\DelvaliasCommand;
use NetServa\Mail\Console\Commands\DelvmailCommand;
use NetServa\Mail\Console\Commands\DkimCommand;
use NetServa\Mail\Console\Commands\ShowMailCommand;
use NetServa\Mail\Console\Commands\ShvaliasCommand;
use NetServa\Mail\Console\Commands\ShvmailCommand;
use NetServa\Mail\Services\DovecotPasswordService;
use NetServa\Mail\Services\DovecotService;
use NetServa\Mail\Services\MailboxService;
use NetServa\Mail\Services\MailServerService;
use NetServa\Mail\Services\PostfixService;
use NetServa\Mail\Services\VmailManagementService;

/**
 * NetServa Mail Service Provider
 */
class NetServaMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailServerService::class);
        $this->app->singleton(MailboxService::class);
        $this->app->singleton(PostfixService::class);
        $this->app->singleton(DovecotService::class);
        $this->app->singleton(DovecotPasswordService::class);
        $this->app->singleton(VmailManagementService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/mail-manager.php', 'mail-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-mail');

        if ($this->app->runningInConsole()) {
            // NetServa CRUD Pattern Commands (mail)
            $this->commands([
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
                // Mail utilities
                DkimCommand::class,
                ShowMailCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mail-manager.php' => config_path('mail-manager.php'),
            ], 'netserva-mail-config');
        }
    }
}
