<?php

declare(strict_types=1);

namespace NetServa\Cms;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * NetServa CMS Filament Plugin
 *
 * CRITICAL: Implements Plugin interface directly (NOT extends BaseFilamentPlugin)
 * This ensures the package can be deployed standalone without any NetServa dependencies
 */
class NetServaCmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-cms';
    }

    public function register(Panel $panel): void
    {
        // Register Filament resources
        // These will be added as we build them
        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'NetServa\\Cms\\Filament\\Resources'
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Pages',
                for: 'NetServa\\Cms\\Filament\\Pages'
            )
            ->discoverWidgets(
                in: __DIR__.'/Filament/Widgets',
                for: 'NetServa\\Cms\\Filament\\Widgets'
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
