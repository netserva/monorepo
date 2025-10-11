<?php

namespace NetServa\Dns\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Dns\Filament\Resources\DnsProviderResource;
use NetServa\Dns\Filament\Resources\DnsRecordResource;
use NetServa\Dns\Filament\Resources\DnsZoneResource;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

/**
 * NetServa DNS Plugin
 *
 * Provides comprehensive DNS and domain management for NetServa infrastructure.
 * Integrates with PowerDNS and multiple domain registrars.
 *
 * Features:
 * - DNS zone management
 * - DNS record management (A, AAAA, CNAME, MX, TXT, etc.)
 * - Domain registration tracking
 * - PowerDNS integration
 * - Multiple DNS provider support
 *
 * @package NetServa\Dns\Filament
 */
class NetServaDnsPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-dns';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            DnsZoneResource::class,
            DnsRecordResource::class,
            DnsProviderResource::class,
            DomainRegistrationResource::class,
            DomainRegistrarResource::class,
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
        // Planned groups: DNS & Domains
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
                'dns_zones' => true,
                'dns_records' => true,
                'domain_registration' => true,
                'powerdns_integration' => true,
                'multi_provider' => true,
            ],
            'settings' => [
                'default_ttl' => 3600,
                'default_nameservers' => ['ns1.netserva.org', 'ns2.netserva.org'],
            ],
        ];
    }
}
