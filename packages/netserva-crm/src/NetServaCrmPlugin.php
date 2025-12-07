<?php

declare(strict_types=1);

namespace NetServa\Crm;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * NetServa CRM Filament Plugin
 *
 * CRITICAL: Implements Plugin interface directly (NOT extends BaseFilamentPlugin)
 * This ensures the package can be deployed standalone without any NetServa dependencies
 */
class NetServaCrmPlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-crm';
    }

    public function register(Panel $panel): void
    {
        // Register Filament resources via auto-discovery
        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'NetServa\\Crm\\Filament\\Resources'
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Pages',
                for: 'NetServa\\Crm\\Filament\\Pages'
            )
            ->discoverWidgets(
                in: __DIR__.'/Filament/Widgets',
                for: 'NetServa\\Crm\\Filament\\Widgets'
            );
    }

    public function boot(Panel $panel): void
    {
        // Boot logic if needed
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
