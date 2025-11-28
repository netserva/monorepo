<?php

declare(strict_types=1);

namespace NetServa\Core;

use Filament\Contracts\Plugin;
use Filament\Panel;
use NetServa\Core\Filament\Resources\AuditLogResource;
use NetServa\Core\Filament\Resources\PluginResource;
use NetServa\Core\Filament\Resources\SettingResource;

/**
 * NetServa Core Plugin
 *
 * Provides foundation functionality for all NetServa plugins.
 * This is the base plugin that other plugins depend on.
 *
 * Also provides system administration resources (merged from admin plugin):
 * - Settings management
 * - Plugin management
 * - Audit log viewing
 */
class CorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-core';
    }

    public function register(Panel $panel): void
    {
        // Register system administration resources (merged from admin plugin)
        $panel->resources([
            SettingResource::class,
            PluginResource::class,
            AuditLogResource::class,
        ]);
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
