<?php

namespace NetServa\Wg\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Wg\Filament\Resources\WireguardPeerResource;
use NetServa\Wg\Filament\Resources\WireguardServerResource;

/**
 * NetServa WireGuard Plugin
 *
 * Provides VPN management via WireGuard for NetServa infrastructure.
 * Integrates with IPAM for automatic IP allocation.
 *
 * Features:
 * - WireGuard server management
 * - Peer configuration and QR codes
 * - Automatic IP allocation from IPAM
 * - Configuration file generation
 *
 * @package NetServa\Wg\Filament
 */
class NetServaWgPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core', 'netserva-ipam'];

    public function getId(): string
    {
        return 'netserva-wg';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
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
        // Planned groups: VPN Services
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
                'wireguard_servers' => true,
                'peer_management' => true,
                'qr_code_generation' => true,
                'ipam_integration' => true,
            ],
            'settings' => [
                'default_port' => 51820,
                'default_network' => '10.0.0.0/24',
            ],
        ];
    }
}
