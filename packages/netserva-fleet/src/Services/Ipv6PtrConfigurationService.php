<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Services\RemoteConnectionService;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;
use NetServa\Dns\Services\DnsRecordManagementService;
use NetServa\Dns\Services\DnsZoneManagementService;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\IpNetwork;

/**
 * IPv6 PTR Configuration Service
 *
 * Multi-package orchestration service for automated IPv6 reverse DNS configuration.
 * Used by both CLI commands and Filament resources (DRY principle).
 *
 * Package Dependencies:
 * - netserva-core: SSH execution (RemoteConnectionService)
 * - netserva-fleet: IPv6 network tracking and utilities (IpNetwork model, merged from IPAM)
 * - netserva-dns: PowerDNS zone and record management
 *
 * Workflow:
 * 1. Detect routed IPv6 range via SSH
 * 2. Register IPv6 network in IPAM
 * 3. Calculate ip6.arpa reverse zone
 * 4. Create reverse DNS zone in PowerDNS
 * 5. Create PTR record
 * 6. Configure Postfix for IPv6 (if mail server)
 * 7. Update SPF records
 * 8. Generate hosting provider delegation instructions
 */
class Ipv6PtrConfigurationService
{
    public function __construct(
        protected RemoteConnectionService $remoteConnection,
        protected DnsZoneManagementService $dnsZoneService,
        protected DnsRecordManagementService $dnsRecordService,
    ) {}

    /**
     * Configure IPv6 PTR for a VNode
     *
     * @param  FleetVnode  $vnode  The vnode to configure
     * @param  array  $options  Configuration options
     * @return array Result with success status and details
     */
    public function configurePtrForVnode(FleetVnode $vnode, array $options = []): array
    {
        try {
            // Validate SSH access
            if (! $vnode->hasSshAccess()) {
                return [
                    'success' => false,
                    'message' => "VNode '{$vnode->name}' has no SSH access configured",
                ];
            }

            Log::info('Starting IPv6 PTR configuration', [
                'vnode' => $vnode->name,
                'fqdn' => $vnode->fqdn,
                'options' => $options,
            ]);

            DB::beginTransaction();

            try {
                // Step 1: Detect routed IPv6 range
                $ipv6Range = $this->detectRoutedIpv6Range($vnode);

                if (! $ipv6Range) {
                    throw new Exception('No routed IPv6 range detected on this server');
                }

                Log::info('Detected IPv6 range', $ipv6Range);

                // Step 2: Register IPv6 network in IPAM
                $network = $this->registerIpv6Network($vnode, $ipv6Range, $options);

                Log::info('IPv6 network registered', [
                    'network_id' => $network->id,
                    'network' => "{$network->network_address}/{$network->prefix_length}",
                ]);

                // Step 3: Calculate reverse zone
                $reverseZone = $network->getIpv6ReverseZone();

                if (! $reverseZone) {
                    throw new Exception('Failed to calculate IPv6 reverse zone');
                }

                Log::info('Calculated reverse zone', ['zone' => $reverseZone]);

                // Step 4: Detect or use override mail server IP
                $mailIp = $options['mail_ip'] ?? $this->detectMailServerIp($vnode);

                if (! $mailIp) {
                    throw new Exception('Could not detect mail server IPv6 address');
                }

                // Validate mail IP is within network
                if (! $network->containsIp($mailIp)) {
                    throw new Exception("Mail IP {$mailIp} is not within network {$network->network_address}/{$network->prefix_length}");
                }

                Log::info('Using mail server IP', ['ip' => $mailIp]);

                // Step 5: Create or verify reverse DNS zone
                $zone = $this->createOrGetReverseZone($reverseZone, $options);

                Log::info('Reverse zone ready', [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                ]);

                // Step 6: Create PTR record
                $ptrRecord = $this->createPtrRecord($network, $zone, $mailIp, $vnode->fqdn, $options);

                Log::info('PTR record created', [
                    'record_id' => $ptrRecord->id,
                    'name' => $ptrRecord->name,
                    'content' => $ptrRecord->content,
                ]);

                // Step 7: Configure Postfix for IPv6 (if not skipped and mail server detected)
                $postfixConfigured = false;
                if (! ($options['skip_postfix'] ?? false)) {
                    if ($this->isMailServer($vnode)) {
                        $postfixResult = $this->configurePostfixIpv6($vnode, $mailIp, $options);
                        $postfixConfigured = $postfixResult['success'];

                        if (! $postfixConfigured) {
                            Log::warning('Postfix configuration failed', $postfixResult);
                        }
                    } else {
                        Log::info('Not a mail server, skipping Postfix configuration');
                    }
                }

                // Step 8: Update SPF record (if not skipped)
                $spfUpdated = false;
                if (! ($options['skip_spf'] ?? false) && $vnode->fqdn) {
                    $spfResult = $this->updateSpfRecord($vnode, $mailIp, $options);
                    $spfUpdated = $spfResult['success'];

                    if (! $spfUpdated) {
                        Log::warning('SPF update failed', $spfResult);
                    }
                }

                // Step 9: Generate delegation instructions
                $delegationInstructions = $this->generateDelegationInstructions(
                    $network,
                    $options['hosting_provider'] ?? $this->detectHostingProvider($vnode)
                );

                DB::commit();

                return [
                    'success' => true,
                    'message' => "IPv6 PTR configured successfully for {$vnode->name}",
                    'ipv6_network' => $network,
                    'reverse_zone' => $zone,
                    'ptr_record' => $ptrRecord,
                    'mail_ip' => $mailIp,
                    'postfix_configured' => $postfixConfigured,
                    'spf_updated' => $spfUpdated,
                    'delegation_instructions' => $delegationInstructions,
                ];

            } catch (Exception $e) {
                DB::rollBack();

                Log::error('IPv6 PTR configuration failed', [
                    'vnode' => $vnode->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'IPv6 PTR configuration failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect routed IPv6 range on remote server
     *
     * @return array|null ['network_address' => string, 'prefix_length' => int, 'detected_ip' => string]
     */
    protected function detectRoutedIpv6Range(FleetVnode $vnode): ?array
    {
        $script = <<<'BASH'
# Detect routed IPv6 range (skip link-local fe80)
ip -6 addr show | grep -oP '2[0-9a-f:]+/\d+' | grep -v '^fe80' | head -1
BASH;

        $result = $this->remoteConnection->exec($vnode->sshHost->host, $script);

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        // Parse: 2404:9400:2198:f600::103/56
        if (preg_match('#^([0-9a-f:]+)/(\d+)$#i', trim($result['output']), $matches)) {
            $fullAddress = $matches[1];
            $prefixLength = (int) $matches[2];

            // Validate prefix length (common ranges for mail servers)
            if (! in_array($prefixLength, [48, 56, 64])) {
                Log::warning('Unusual IPv6 prefix length detected', [
                    'prefix' => $prefixLength,
                    'address' => $fullAddress,
                ]);
            }

            return [
                'network_address' => $this->getNetworkAddress($fullAddress, $prefixLength),
                'prefix_length' => $prefixLength,
                'detected_ip' => $fullAddress,
            ];
        }

        return null;
    }

    /**
     * Calculate network address from IPv6 address and prefix length
     *
     * Example: 2404:9400:2198:f600::103 /56 → 2404:9400:2198:f600::
     */
    protected function getNetworkAddress(string $ipv6Address, int $prefixLength): string
    {
        // Create temporary IpNetwork to use expandIpv6 and binary conversion
        $tempNetwork = new IpNetwork([
            'network_address' => $ipv6Address,
            'prefix_length' => $prefixLength,
            'ip_version' => '6',
        ]);

        // Expand to full form
        $reflection = new \ReflectionClass($tempNetwork);
        $expandMethod = $reflection->getMethod('expandIpv6');
        $expandMethod->setAccessible(true);
        $expanded = $expandMethod->invoke($tempNetwork, $ipv6Address);

        // Convert to binary
        $binaryMethod = $reflection->getMethod('ipv6ToBinary');
        $binaryMethod->setAccessible(true);
        $binary = $binaryMethod->invoke($tempNetwork, $expanded);

        // Zero out host bits
        $networkBits = substr($binary, 0, $prefixLength);
        $hostBits = str_repeat('0', 128 - $prefixLength);
        $fullBinary = $networkBits.$hostBits;

        // Convert back to IPv6
        $hex = '';
        for ($i = 0; $i < 128; $i += 4) {
            $nibble = substr($fullBinary, $i, 4);
            $hex .= base_convert($nibble, 2, 16);
        }

        // Format as IPv6 (groups of 4 hex digits)
        $groups = str_split($hex, 4);
        $ipv6 = implode(':', $groups);

        // Compress (replace longest run of 0000 with ::)
        return inet_ntop(inet_pton($ipv6));
    }

    /**
     * Register IPv6 network in IPAM
     */
    protected function registerIpv6Network(FleetVnode $vnode, array $ipv6Range, array $options): IpNetwork
    {
        // Check if network already exists
        $existing = IpNetwork::where('network_address', $ipv6Range['network_address'])
            ->where('prefix_length', $ipv6Range['prefix_length'])
            ->where('ip_version', '6')
            ->first();

        if ($existing) {
            Log::info('IPv6 network already registered', ['network_id' => $existing->id]);

            return $existing;
        }

        // Create new network
        // Note: infrastructure_node_id references infrastructure_nodes table (not fleet_vnodes)
        // Set to null for now - TODO: fix foreign key constraint in ipam package
        return IpNetwork::create([
            'name' => $options['network_name'] ?? "{$vnode->name} Routed IPv6 Range",
            'description' => "Routed IPv6 prefix for {$vnode->fqdn} (VNode ID: {$vnode->id})",
            'network_address' => $ipv6Range['network_address'],
            'prefix_length' => $ipv6Range['prefix_length'],
            'ip_version' => '6',
            'network_type' => 'public',
            'gateway' => $options['gateway'] ?? null,
            'infrastructure_node_id' => null, // TODO: Fix FK constraint - should reference fleet_vnodes
            'is_active' => true,
        ]);
    }

    /**
     * Detect mail server IPv6 address
     */
    protected function detectMailServerIp(FleetVnode $vnode): ?string
    {
        // Try Postfix configuration first
        $script = <<<'BASH'
# Check Postfix smtp_bind_address6
if [ -f /etc/postfix/main.cf ]; then
    grep '^smtp_bind_address6' /etc/postfix/main.cf 2>/dev/null | awk '{print $3}'
fi
BASH;

        $result = $this->remoteConnection->exec($vnode->sshHost->host, $script);

        if ($result['success'] && ! empty(trim($result['output']))) {
            $ip = trim($result['output']);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $ip;
            }
        }

        // Fallback: Use primary IPv6 address
        $script = <<<'BASH'
ip -6 addr show | grep -oP '2[0-9a-f:]+/\d+' | grep -v '^fe80' | head -1 | cut -d'/' -f1
BASH;

        $result = $this->remoteConnection->exec($vnode->sshHost->host, $script);

        if ($result['success']) {
            $ip = trim($result['output']);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Create or get existing reverse DNS zone
     */
    protected function createOrGetReverseZone(string $reverseZone, array $options): mixed
    {
        // Find DNS provider
        $providerName = $options['dns_provider'] ?? 'ns1rn';
        $provider = DnsProvider::where('vnode', $providerName)
            ->orWhere('name', $providerName)
            ->first();

        if (! $provider) {
            throw new Exception("DNS provider '{$providerName}' not found");
        }

        // Check if zone already exists in local database
        $existingZone = $this->dnsZoneService->listZones([
            'provider' => $provider->id,
            'search' => rtrim($reverseZone, '.'),
        ])->first();

        if ($existingZone) {
            Log::info('Reverse zone already exists', ['zone_id' => $existingZone->id]);

            return $existingZone;
        }

        // Create new reverse zone
        $zoneResult = $this->dnsZoneService->createZone(
            $reverseZone,
            $provider->id,
            [
                'kind' => 'Primary',
                'nameservers' => $options['nameservers'] ?? [
                    'ns1.renta.net.',
                    'ns2.renta.net.',
                    'ns3.renta.net.',
                ],
                'admin_email' => $options['admin_email'] ?? 'admin@renta.net',
                'ttl' => $options['ttl'] ?? 3600,
                'create_default_records' => true,
                'skip_test' => $options['dry_run'] ?? false,
            ]
        );

        // If creation failed with 409 Conflict, zone exists remotely but not in local DB
        if (! $zoneResult['success']) {
            $isConflict = str_contains($zoneResult['message'] ?? '', '409') ||
                str_contains($zoneResult['message'] ?? '', 'Conflict') ||
                str_contains($zoneResult['message'] ?? '', 'already exists');

            if ($isConflict) {
                // Zone exists remotely but not in local DB - create local record
                Log::info('Zone exists remotely, creating local database record', [
                    'zone' => $reverseZone,
                    'provider' => $provider->name,
                ]);

                // Create local DnsZone record for the existing remote zone
                $localZone = DnsZone::create([
                    'dns_provider_id' => $provider->id,
                    'external_id' => rtrim($reverseZone, '.'),
                    'name' => $reverseZone,
                    'kind' => 'Primary',
                    'masters' => null,
                    'ttl' => $options['ttl'] ?? 3600,
                    'active' => true,
                    'description' => 'IPv6 reverse zone (imported from existing PowerDNS zone)',
                    'auto_dnssec' => false,
                    'dnssec_enabled' => false,
                    'nameservers' => $options['nameservers'] ?? [
                        'ns1.renta.net.',
                        'ns2.renta.net.',
                        'ns3.renta.net.',
                    ],
                    'sort_order' => 0,
                    'provider_data' => ['imported' => true, 'reason' => '409_conflict'],
                    'last_synced' => now(),
                ]);

                Log::info('Created local zone record for existing remote zone', [
                    'zone_id' => $localZone->id,
                    'zone_name' => $localZone->name,
                ]);

                return $localZone;
            }

            throw new Exception('Failed to create reverse zone: '.$zoneResult['message']);
        }

        return $zoneResult['zone'];
    }

    /**
     * Create PTR record for mail server
     */
    protected function createPtrRecord(
        IpNetwork $network,
        mixed $zone,
        string $mailIp,
        string $mailFqdn,
        array $options
    ): mixed {
        // Calculate PTR label (host portion only)
        $ptrLabel = $network->getIpv6PtrLabel($mailIp);

        if (! $ptrLabel) {
            throw new Exception("Failed to calculate PTR label for {$mailIp}");
        }

        // Ensure FQDN has trailing dot
        $ptrContent = rtrim($mailFqdn, '.').'.';

        Log::info('Creating PTR record', [
            'label' => $ptrLabel,
            'zone' => $zone->name,
            'content' => $ptrContent,
        ]);

        // Create PTR record
        // NOTE: Pass only the label, not the full name - DnsRecordManagementService will append zone
        $recordResult = $this->dnsRecordService->createRecord(
            'PTR',              // type
            $ptrLabel,          // name (label only, zone will be appended)
            $zone->id,          // zoneId
            $ptrContent,        // content
            [                   // options
                'ttl' => $options['ttl'] ?? 3600,
                'disabled' => false,
            ]
        );

        if (! $recordResult['success']) {
            throw new Exception('Failed to create PTR record: '.$recordResult['message']);
        }

        return $recordResult['record'];
    }

    /**
     * Check if vnode is a mail server
     */
    protected function isMailServer(FleetVnode $vnode): bool
    {
        // Check for Postfix
        $script = <<<'BASH'
if [ -f /etc/postfix/main.cf ]; then
    echo "mail_server"
fi
BASH;

        $result = $this->remoteConnection->exec($vnode->sshHost->host, $script);

        return $result['success'] && str_contains($result['output'], 'mail_server');
    }

    /**
     * Configure Postfix for IPv6
     */
    protected function configurePostfixIpv6(FleetVnode $vnode, string $mailIp, array $options): array
    {
        if ($options['dry_run'] ?? false) {
            return [
                'success' => true,
                'message' => 'Dry run - Postfix configuration skipped',
            ];
        }

        $script = <<<BASH
#!/bin/sh
# NetServa 3.0: Configure Postfix for IPv6

# Backup existing config
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup.\$(date +%Y%m%d_%H%M%S)

# Set inet_protocols = all (IPv4 + IPv6)
postconf -e 'inet_protocols = all'

# Set specific IPv6 binding
postconf -e 'smtp_bind_address6 = {$mailIp}'

# Reload Postfix using sc() function
if command -v sc >/dev/null 2>&1; then
    sc reload postfix
elif command -v systemctl >/dev/null 2>&1; then
    systemctl reload postfix
elif command -v rc-service >/dev/null 2>&1; then
    rc-service postfix reload
else
    /etc/init.d/postfix reload
fi

echo "SUCCESS: Postfix configured for IPv6"
BASH;

        try {
            $result = $this->remoteConnection->exec($vnode->sshHost->host, $script);

            if ($result['success'] && str_contains($result['output'], 'SUCCESS')) {
                return [
                    'success' => true,
                    'message' => 'Postfix configured for IPv6',
                ];
            }

            return [
                'success' => false,
                'message' => 'Postfix configuration failed',
                'output' => $result['output'],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Postfix configuration error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update SPF record to include IPv6
     *
     * TODO: Implement automatic SPF record updates
     * For now, returns success with manual instructions
     */
    protected function updateSpfRecord(FleetVnode $vnode, string $mailIp, array $options): array
    {
        // Extract domain from FQDN (e.g., mail.renta.net → renta.net)
        $fqdnParts = explode('.', $vnode->fqdn);
        if (count($fqdnParts) < 2) {
            return [
                'success' => false,
                'message' => 'Invalid FQDN for SPF update',
            ];
        }

        $domain = implode('.', array_slice($fqdnParts, -2));

        // Manual SPF update instructions
        $message = "Manual SPF update required:\n";
        $message .= "Add ip6:{$mailIp} to SPF record for {$domain}\n";
        $message .= "Example: v=spf1 ... ip6:{$mailIp} ~all";

        Log::info('SPF update (manual)', [
            'domain' => $domain,
            'ipv6' => $mailIp,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'manual_action_required' => true,
        ];
    }

    /**
     * Detect hosting provider from vnode metadata
     */
    protected function detectHostingProvider(FleetVnode $vnode): string
    {
        // Check vsite/venue metadata for provider hints
        if ($vnode->vsite && $vnode->vsite->provider) {
            $provider = strtolower($vnode->vsite->provider);

            if (str_contains($provider, 'binary') || str_contains($provider, 'lane')) {
                return 'binary_lane';
            }

            if (str_contains($provider, 'vultr')) {
                return 'vultr';
            }
        }

        // Default to generic
        return 'generic';
    }

    /**
     * Generate hosting provider delegation instructions
     */
    protected function generateDelegationInstructions(IpNetwork $network, string $provider): array
    {
        $reverseZone = $network->getIpv6ReverseZone();

        $instructions = match ($provider) {
            'binary_lane' => <<<INSTRUCTIONS

            Binary Lane IPv6 Reverse DNS Delegation
            ========================================

            1. Log in to Binary Lane Control Panel
            2. Navigate to your server: Networking → IPv6
            3. Find the routed prefix: {$network->network_address}/{$network->prefix_length}
            4. Click "Configure Reverse DNS"
            5. Select "Delegate to custom nameservers"
            6. Enter nameservers:
               - ns1.renta.net
               - ns2.renta.net
               - ns3.renta.net
            7. Save changes

            Reverse zone created: {$reverseZone}

            INSTRUCTIONS,

            'vultr' => <<<INSTRUCTIONS

            Vultr IPv6 Reverse DNS Delegation
            ==================================

            1. Log in to Vultr Customer Portal
            2. Go to Products → Your Server
            3. Navigate to Settings → IPv6
            4. Find prefix: {$network->network_address}/{$network->prefix_length}
            5. Click "Add PTR Record"
            6. For delegation, contact Vultr support:
               - Request: Delegate {$reverseZone} to ns1-3.renta.net

            INSTRUCTIONS,

            default => <<<INSTRUCTIONS

            Generic IPv6 Reverse DNS Delegation
            ====================================

            Contact your hosting provider to delegate the reverse zone:

            Zone to delegate: {$reverseZone}
            Nameservers:
              - ns1.renta.net
              - ns2.renta.net
              - ns3.renta.net

            INSTRUCTIONS,
        };

        return [
            'zone' => $reverseZone,
            'nameservers' => [
                'ns1.renta.net',
                'ns2.renta.net',
                'ns3.renta.net',
            ],
            'provider' => $provider,
            'provider_instructions' => $instructions,
        ];
    }

    /**
     * Test PTR configuration
     */
    public function testPtrConfiguration(FleetVnode $vnode): array
    {
        try {
            $mailIp = $this->detectMailServerIp($vnode);

            if (! $mailIp) {
                return [
                    'success' => false,
                    'message' => 'Could not detect mail server IPv6 address',
                ];
            }

            // Test PTR lookup using dig
            $script = "dig +short -x {$mailIp}";

            $result = Process::timeout(10)->run($script);

            if (! $result->successful()) {
                return [
                    'success' => false,
                    'message' => 'PTR lookup failed - dig command error',
                ];
            }

            $ptrResult = rtrim(trim($result->output()), '.');
            $expectedFqdn = rtrim($vnode->fqdn, '.');

            if ($ptrResult === $expectedFqdn) {
                return [
                    'success' => true,
                    'message' => "PTR record resolves correctly: {$mailIp} → {$ptrResult}",
                    'ptr_record' => $ptrResult,
                    'mail_ip' => $mailIp,
                ];
            }

            return [
                'success' => false,
                'message' => "PTR mismatch: expected {$expectedFqdn}, got {$ptrResult}",
                'expected' => $expectedFqdn,
                'actual' => $ptrResult,
                'mail_ip' => $mailIp,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'PTR test error: '.$e->getMessage(),
            ];
        }
    }
}
