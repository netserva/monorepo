<?php

declare(strict_types=1);

namespace NetServa\Core;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * NetServa Core Plugin
 *
 * Provides foundation functionality for all NetServa plugins.
 * This is the base plugin that other plugins depend on.
 */
class CorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-core';
    }

    public function register(Panel $panel): void
    {
        // Core plugin doesn't register Filament resources directly
        // It provides base models, services, and foundation classes for other plugins
    }

    public function boot(Panel $panel): void
    {
        // Core plugin boot logic if needed
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return '3.0.0';
    }

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Get default configuration
     */
    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [],
            'settings' => [],
        ];
    }
}
