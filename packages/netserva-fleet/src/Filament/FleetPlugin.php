<?php

namespace NetServa\Fleet\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Fleet\Filament\Resources\FleetVHostResource;
use NetServa\Fleet\Filament\Resources\FleetVNodeResource;
use NetServa\Fleet\Filament\Resources\FleetVSiteResource;

/**
 * NetServa Fleet Plugin
 *
 * Provides fleet-wide management of VNodes, VHosts, and VSites across
 * the NetServa infrastructure.
 *
 * Features:
 * - VNode (server node) management
 * - VHost (virtual host) management
 * - VSite (site group) management
 * - Fleet-wide discovery and monitoring
 *
 * @package NetServa\Fleet\Filament
 */
class FleetPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-fleet';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            FleetVNodeResource::class,
            FleetVHostResource::class,
            FleetVSiteResource::class,
        ]);
    }

    protected function registerPages(Panel $panel): void
    {
        // No custom pages currently
    }

    protected function registerWidgets(Panel $panel): void
    {
        // No widgets currently
    }

    protected function registerNavigationItems(Panel $panel): void
    {
        // TODO: Navigation groups should be defined in Resource classes as protected static properties
        // This is the Filament 4.x pattern. For now, resources will use default navigation.
        //
        // Planned groups: Fleet Management
    }

    public function getVersion(): string
    {
        return '3.0.0';
    }

    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [
                'vnode_management' => true,
                'vhost_management' => true,
                'vsite_management' => true,
                'fleet_discovery' => true,
            ],
            'settings' => [
                'auto_discover_interval' => 300, // 5 minutes
                'fleet_monitoring_enabled' => true,
            ],
        ];
    }
}
