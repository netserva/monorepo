<?php

namespace NetServa\Core;

use Filament\Panel;
use NetServa\Core\Filament\Resources\MountPointResource;
use NetServa\Core\Filament\Resources\SystemServiceResource;
use NetServa\Core\Filament\Resources\VHostResource;
use NetServa\Core\Filament\Widgets\SystemStatsWidget;
use NetServa\Core\Foundation\BaseFilamentPlugin;

/**
 * NetServa Core Plugin
 *
 * Foundation plugin providing core infrastructure resources and services.
 * All other NetServa plugins depend on this core plugin.
 *
 * Provides:
 * - System service management
 * - Virtual host (VHost) management
 * - Mount point management
 * - System statistics widget
 *
 * @package NetServa\Core
 */
class CorePlugin extends BaseFilamentPlugin
{
    /**
     * No dependencies - this is the foundation plugin
     */
    protected array $dependencies = [];

    /**
     * Plugin identifier
     */
    public function getId(): string
    {
        return 'netserva-core';
    }

    /**
     * Register core Filament resources
     */
    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            SystemServiceResource::class,
            VHostResource::class,
            MountPointResource::class,
        ]);
    }

    /**
     * Register core pages (none currently)
     */
    protected function registerPages(Panel $panel): void
    {
        // No custom pages in core currently
    }

    /**
     * Register core widgets
     */
    protected function registerWidgets(Panel $panel): void
    {
        $panel->widgets([
            SystemStatsWidget::class,
        ]);
    }

    /**
     * Configure navigation grouping for core resources
     */
    protected function registerNavigationItems(Panel $panel): void
    {
        // TODO: Navigation groups should be defined in Resource classes as protected static properties
        // This is the Filament 4.x pattern. For now, resources will use default navigation.
        //
        // Planned group: Core Infrastructure
        // - SystemServiceResource, VHostResource, MountPointResource
    }

    /**
     * Core-specific boot logic
     */
    protected function bootPlugin(Panel $panel): void
    {
        // Core initialization logic if needed
        // This runs after all plugins are registered
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return '3.0.0';
    }

    /**
     * Get default configuration
     */
    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [
                'system_services' => true,
                'vhost_management' => true,
                'mount_points' => true,
                'system_stats' => true,
            ],
            'settings' => [
                'auto_discover_services' => true,
                'system_monitoring_interval' => 60, // seconds
            ],
        ];
    }
}
