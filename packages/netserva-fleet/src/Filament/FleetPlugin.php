<?php

namespace NetServa\Fleet\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Fleet\Filament\Clusters\Fleet\FleetCluster;
use NetServa\Fleet\Filament\Clusters\Network\NetworkCluster;
use NetServa\Fleet\Filament\Resources\FleetVenueResource;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource;
use NetServa\Fleet\Filament\Resources\IpAddressResource;
use NetServa\Fleet\Filament\Resources\IpNetworkResource;
use NetServa\Fleet\Filament\Resources\IpReservationResource;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource;
use NetServa\Fleet\Filament\Resources\WireguardServerResource;

/**
 * NetServa Fleet Plugin
 *
 * Provides fleet-wide management of Venues, VSites, VNodes, VHosts, IP address
 * management (IPAM), and WireGuard VPN across the NetServa infrastructure.
 *
 * Features:
 * - Venue (infrastructure location) management
 * - VSite (site group) management
 * - VNode (server node) management
 * - VHost (virtual host) management
 * - Fleet-wide discovery and monitoring
 * - IP Network management (merged from IPAM)
 * - IP Address allocation and tracking (merged from IPAM)
 * - IP Reservations (merged from IPAM)
 * - WireGuard VPN server management (merged from WG)
 * - WireGuard peer management (merged from WG)
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
        // Register clusters for collapsible navigation
        $panel->clusters([
            FleetCluster::class,
            NetworkCluster::class,
        ]);

        $panel->resources([
            // Fleet core resources (under FleetCluster)
            FleetVenueResource::class,
            FleetVsiteResource::class,
            FleetVnodeResource::class,
            FleetVhostResource::class,
            // Network resources (under NetworkCluster)
            IpNetworkResource::class,
            IpAddressResource::class,
            IpReservationResource::class,
            WireguardServerResource::class,
            WireguardPeerResource::class,
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
                // IPAM features (merged from netserva-ipam)
                'ip_network_management' => true,
                'ip_address_tracking' => true,
                'ip_reservations' => true,
                // WireGuard features (merged from netserva-wg)
                'wireguard_servers' => true,
                'wireguard_peers' => true,
            ],
            'settings' => [
                'auto_discover_interval' => 300, // 5 minutes
                'fleet_monitoring_enabled' => true,
                // IPAM settings
                'default_ip_version' => '4',
                'auto_allocate_ips' => true,
                // WireGuard settings
                'wireguard_default_port' => 51820,
            ],
        ];
    }
}
