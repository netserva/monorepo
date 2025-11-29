<?php

declare(strict_types=1);

namespace NetServa\Core;

use Filament\Contracts\Plugin;
use Filament\Panel;
use NetServa\Core\Filament\Pages\CommandRunner;
use NetServa\Core\Filament\Pages\Dashboard;
use NetServa\Core\Filament\Pages\SshTerminal;
use NetServa\Core\Filament\Resources\AuditLogResource;
use NetServa\Core\Filament\Resources\PluginResource;
use NetServa\Core\Filament\Resources\SettingResource;
use NetServa\Core\Filament\Resources\SshHostResource;
use NetServa\Core\Filament\Resources\SshKeyResource;
use NetServa\Core\Filament\Widgets\InfrastructureOverview;
use NetServa\Core\Filament\Widgets\ServiceHealthStatus;
use NetServa\Core\Filament\Widgets\SystemStatsOverview;

/**
 * NetServa Core Plugin
 *
 * Provides foundation functionality for all NetServa plugins.
 * This is the base plugin that other plugins depend on.
 *
 * Core navigation group:
 * - SSH Terminal (execute remote commands)
 * - SSH Hosts (manage SSH host configs)
 * - SSH Keys (manage SSH key pairs)
 * - Commands (execute artisan commands)
 * - Settings, Plugins, Audit Log
 */
class CorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-core';
    }

    public function register(Panel $panel): void
    {
        // Register Core pages (including Dashboard)
        $panel->pages([
            Dashboard::class,
            SshTerminal::class,
            CommandRunner::class,
        ]);

        // Register Core resources
        $panel->resources([
            SshHostResource::class,
            SshKeyResource::class,
            SettingResource::class,
            PluginResource::class,
            AuditLogResource::class,
        ]);

        // Register Core widgets
        $panel->widgets([
            SystemStatsOverview::class,
            InfrastructureOverview::class,
            ServiceHealthStatus::class,
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
