<?php

namespace NetServa\Mail;

use Illuminate\Support\ServiceProvider;
use NetServa\Mail\Services\DovecotService;
use NetServa\Mail\Services\MailboxService;
use NetServa\Mail\Services\MailServerService;
use NetServa\Mail\Services\PostfixService;

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

        $this->mergeConfigFrom(__DIR__.'/../config/mail-manager.php', 'mail-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-mail');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/mail-manager.php' => config_path('mail-manager.php'),
            ], 'netserva-mail-config');
        }
    }
}
