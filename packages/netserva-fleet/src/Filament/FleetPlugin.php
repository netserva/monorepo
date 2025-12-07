<?php

namespace NetServa\Fleet\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource;
use NetServa\Fleet\Filament\Resources\IpamResource;
use NetServa\Fleet\Filament\Resources\WireguardResource;

/**
 * NetServa Fleet Plugin
 *
 * Provides fleet-wide management of VSites, VNodes, VHosts, IP address
 * management (IPAM), and WireGuard VPN across the NetServa infrastructure.
 *
 * Features:
 * - VSite (site group) management with provider/location/owner
 * - VNode (server node) management with ssh_host/hostname/fqdn
 * - VHost (virtual host) management with uid/gid/ssl
 * - Fleet-wide discovery and monitoring
 * - IPAM (unified IP network, address, and reservation management)
 * - WireGuard VPN server management
 * - WireGuard peer management
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
            // Fleet resources (vsite → vnode → vhost hierarchy)
            FleetVsiteResource::class,
            FleetVnodeResource::class,
            FleetVhostResource::class,
            // IPAM (unified network/address/reservation management)
            IpamResource::class,
            // WireGuard VPN (unified server/peer management)
            WireguardResource::class,
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
        // Navigation groups are defined in Resource classes
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
                'vsite_management' => true,
                'vnode_management' => true,
                'vhost_management' => true,
                'fleet_discovery' => true,
                // BinaryLane VPS provider integration
                'binarylane_management' => true,
                // IPAM features
                'ip_network_management' => true,
                'ip_address_tracking' => true,
                'ip_reservations' => true,
                // WireGuard features
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
