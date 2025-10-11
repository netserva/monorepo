<?php

namespace NetServa\Ipam\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Ipam\Filament\Resources\IpAddressResource;
use NetServa\Ipam\Filament\Resources\IpNetworkResource;
use NetServa\Ipam\Filament\Resources\IpReservationResource;

/**
 * NetServa IPAM Plugin
 *
 * Provides IP Address Management (IPAM) for NetServa infrastructure.
 *
 * Features:
 * - IP network management and CIDR calculations
 * - IP address allocation and tracking
 * - IP reservation system
 * - Integration with VPN and server provisioning
 *
 * @package NetServa\Ipam\Filament
 */
class NetServaIpamPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-ipam';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            IpNetworkResource::class,
            IpAddressResource::class,
            IpReservationResource::class,
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
        // Planned groups: IP Address Management
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
                'network_management' => true,
                'address_allocation' => true,
                'reservations' => true,
                'cidr_calculations' => true,
            ],
            'settings' => [
                'default_network_size' => 24,
                'auto_assign_addresses' => true,
            ],
        ];
    }
}
