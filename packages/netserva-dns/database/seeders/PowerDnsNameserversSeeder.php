<?php

namespace NetServa\Dns\Database\Seeders;

use Illuminate\Database\Seeder;
use NetServa\Dns\Models\DnsProvider;

/**
 * PowerDNS Nameservers Seeder
 *
 * Seeds the additional PowerDNS nameserver providers for Gold Coast and Renta.net infrastructure.
 * This complements the existing "goldcoast" (ns1gc) and "rentanet" (ns1rn) providers.
 *
 * Usage:
 *   php artisan db:seed --class="NetServa\Dns\Database\Seeders\PowerDnsNameserversSeeder"
 *
 * Infrastructure:
 * - Gold Coast: ns1gc (existing), ns2gc, ns3gc
 * - Renta.net: ns1rn (existing), ns2rn, ns3rn
 */
class PowerDnsNameserversSeeder extends Seeder
{
    /**
     * Seed the PowerDNS nameserver providers
     */
    public function run(): void
    {
        $providers = [
            // Gold Coast Nameservers (ns2 and ns3)
            [
                'type' => 'powerdns',
                'name' => 'goldcoast-ns2',
                'description' => 'Gold Coast PowerDNS Nameserver 2 (ns2gc) - Secondary authoritative DNS',
                'connection_config' => [
                    'ssh_host' => 'ns2gc',
                    'api_key' => 'pdns_api_ns2gc_31042a8b414d4dccefa65f1c6856f402',
                    'api_port' => 8081,
                    'timeout' => 30,
                ],
                'active' => true,
                'sort_order' => 10,
                'rate_limit' => 100,
                'timeout' => 30,
            ],
            [
                'type' => 'powerdns',
                'name' => 'goldcoast-ns3',
                'description' => 'Gold Coast PowerDNS Nameserver 3 (ns3gc) - Tertiary authoritative DNS',
                'connection_config' => [
                    'ssh_host' => 'ns3gc',
                    'api_key' => 'pdns_api_ns3gc_ebd6e12f8dee91d13cfaf96dc0e056ea',
                    'api_port' => 8081,
                    'timeout' => 30,
                ],
                'active' => true,
                'sort_order' => 11,
                'rate_limit' => 100,
                'timeout' => 30,
            ],

            // Renta.net Nameservers (ns2 and ns3)
            [
                'type' => 'powerdns',
                'name' => 'rentanet-ns2',
                'description' => 'Renta.net PowerDNS Nameserver 2 (ns2rn) - Secondary authoritative DNS',
                'connection_config' => [
                    'ssh_host' => 'ns2rn',
                    'api_key' => 'pdns_api_ns2_d6f46d945448eeb656350f30c849d09e',
                    'api_port' => 8081,
                    'timeout' => 30,
                ],
                'active' => true,
                'sort_order' => 12,
                'rate_limit' => 100,
                'timeout' => 30,
            ],
            [
                'type' => 'powerdns',
                'name' => 'rentanet-ns3',
                'description' => 'Renta.net PowerDNS Nameserver 3 (ns3rn) - Tertiary authoritative DNS',
                'connection_config' => [
                    'ssh_host' => 'ns3rn',
                    'api_key' => 'pdns_api_ns3_c5b46d7b164043b0791b0117c56b0b23',
                    'api_port' => 8081,
                    'timeout' => 30,
                ],
                'active' => true,
                'sort_order' => 13,
                'rate_limit' => 100,
                'timeout' => 30,
            ],
        ];

        foreach ($providers as $providerData) {
            DnsProvider::updateOrCreate(
                ['name' => $providerData['name']],
                $providerData
            );

            $this->command->info("✅ Created/updated DNS provider: {$providerData['name']} ({$providerData['connection_config']['ssh_host']})");
        }

        $this->command->line('');
        $this->command->info('🎉 PowerDNS nameserver providers seeded successfully!');
        $this->command->line('');
        $this->command->line('Total PowerDNS providers: '.DnsProvider::where('type', 'powerdns')->count());
        $this->command->line('');
        $this->command->line('Test tunnel creation:');
        $this->command->line('  php artisan tunnel create ns2gc powerdns');
        $this->command->line('  php artisan tunnel create ns3gc powerdns');
    }
}
