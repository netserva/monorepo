<?php

namespace NetServa\Fleet\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Fleet\Filament\Resources\FleetVenueResource;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource;

/**
 * NetServa Fleet Plugin
 *
 * Provides fleet-wide management of Venues, VSites, VNodes, and VHosts across
 * the NetServa infrastructure.
 *
 * Features:
 * - Venue (infrastructure location) management
 * - VSite (site group) management
 * - VNode (server node) management
 * - VHost (virtual host) management
 * - Fleet-wide discovery and monitoring
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
            FleetVenueResource::class,
            FleetVsiteResource::class,
            FleetVnodeResource::class,
            FleetVhostResource::class,
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
                'venue_management' => true,
                'vsite_management' => true,
                'vnode_management' => true,
                'vhost_management' => true,
                'fleet_discovery' => true,
            ],
            'settings' => [
                'auto_discover_interval' => 300, // 5 minutes
                'fleet_monitoring_enabled' => true,
            ],
        ];
    }
}
