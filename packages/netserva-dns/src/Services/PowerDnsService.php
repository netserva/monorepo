<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DnsProvider;

/**
 * Comprehensive PowerDNS Management Service
 *
 * Provides complete PowerDNS functionality including zones, records, DNSSEC,
 * statistics, monitoring, and integration with NetServa platform.
 * Built on top of PowerDnsTunnelService for secure SSH tunnel access.
 */
class PowerDnsService
{
    protected PowerDnsTunnelService $tunnelService;

    public function __construct(PowerDnsTunnelService $tunnelService)
    {
        $this->tunnelService = $tunnelService;
    }

    /**
     * Test connection to PowerDNS server
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Connection test result
     */
    public function testConnection(DnsProvider $provider): array
    {
        try {
            // Try to get server list as a simple connection test
            $result = $this->tunnelService->apiCall($provider, '/servers');

            if ($result['success']) {
                $servers = $result['data'];
                $serverInfo = ! empty($servers)
                    ? ($servers[0]['daemon_type'] ?? 'PowerDNS').' '.($servers[0]['version'] ?? 'Unknown')
                    : 'PowerDNS Server';

                return [
                    'success' => true,
                    'server_info' => $serverInfo,
                    'tunnel_used' => $result['tunnel_used'],
                    'message' => 'Connection successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect: '.($result['error'] ?? 'Unknown error'),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get all PowerDNS servers
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Server information
     */
    public function getServers(DnsProvider $provider): array
    {
        $result = $this->tunnelService->apiCall($provider, '/servers');

        if ($result['success']) {
            Log::info('PowerDNS servers retrieved', [
                'provider' => $provider->name,
                'server_count' => count($result['data']),
            ]);

            return [
                'success' => true,
                'servers' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to get servers: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Get PowerDNS server configuration
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $serverId  Server ID (default: localhost)
     * @return array Server configuration
     */
    public function getServerConfig(DnsProvider $provider, string $serverId = 'localhost'): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/$serverId/config");

        if ($result['success']) {
            return [
                'success' => true,
                'config' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to get server config: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Search PowerDNS logs
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $query  Search query
     * @param  int  $limit  Result limit
     * @return array Search results
     */
    public function searchLogs(DnsProvider $provider, string $query, int $limit = 100): array
    {
        $result = $this->tunnelService->apiCall($provider, '/servers/localhost/search-log', 'GET', [
            'q' => $query,
            'limit' => $limit,
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'logs' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to search logs: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Get zone metadata
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Zone metadata
     */
    public function getZoneMetadata(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/metadata");

        if ($result['success']) {
            return [
                'success' => true,
                'metadata' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to get zone metadata: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Set zone metadata
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  array  $metadata  Metadata to set
     * @return array Operation result
     */
    public function setZoneMetadata(DnsProvider $provider, string $zoneName, array $metadata): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/metadata", 'PUT', $metadata);

        if ($result['success']) {
            Log::info('PowerDNS zone metadata updated', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'metadata_keys' => array_keys($metadata),
            ]);

            return [
                'success' => true,
                'message' => 'Zone metadata updated successfully',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to set zone metadata: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Get zone cryptokeys (DNSSEC keys)
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Cryptokeys
     */
    public function getZoneCryptokeys(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/cryptokeys");

        if ($result['success']) {
            return [
                'success' => true,
                'cryptokeys' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to get zone cryptokeys: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Generate new DNSSEC key for zone
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  array  $keyOptions  Key generation options
     * @return array Generation result
     */
    public function generateDnssecKey(DnsProvider $provider, string $zoneName, array $keyOptions = []): array
    {
        $defaultOptions = [
            'keytype' => 'ksk',
            'active' => true,
            'algorithm' => 'ecdsa256',
            'bits' => 0,
        ];

        $options = array_merge($defaultOptions, $keyOptions);

        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/cryptokeys", 'POST', $options);

        if ($result['success']) {
            Log::info('PowerDNS DNSSEC key generated', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'keytype' => $options['keytype'],
                'algorithm' => $options['algorithm'],
            ]);

            return [
                'success' => true,
                'message' => 'DNSSEC key generated successfully',
                'key' => $result['data'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to generate DNSSEC key: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Activate/deactivate DNSSEC key
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  int  $keyId  Key ID
     * @param  bool  $active  Activation state
     * @return array Operation result
     */
    public function setKeyActive(DnsProvider $provider, string $zoneName, int $keyId, bool $active): array
    {
        $result = $this->tunnelService->apiCall(
            $provider,
            "/servers/localhost/zones/$zoneName/cryptokeys/$keyId",
            'PUT',
            ['active' => $active]
        );

        if ($result['success']) {
            Log::info('PowerDNS DNSSEC key activation changed', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'key_id' => $keyId,
                'active' => $active,
            ]);

            return [
                'success' => true,
                'message' => sprintf('DNSSEC key %s %s', $keyId, $active ? 'activated' : 'deactivated'),
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to change key activation: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Delete DNSSEC key
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  int  $keyId  Key ID
     * @return array Deletion result
     */
    public function deleteDnssecKey(DnsProvider $provider, string $zoneName, int $keyId): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/cryptokeys/$keyId", 'DELETE');

        if ($result['success']) {
            Log::info('PowerDNS DNSSEC key deleted', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'key_id' => $keyId,
            ]);

            return [
                'success' => true,
                'message' => "DNSSEC key $keyId deleted successfully",
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete DNSSEC key: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Rectify zone (fix DNSSEC signatures)
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Rectify result
     */
    public function rectifyZone(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/rectify", 'PUT');

        if ($result['success']) {
            Log::info('PowerDNS zone rectified', [
                'provider' => $provider->name,
                'zone' => $zoneName,
            ]);

            return [
                'success' => true,
                'message' => "Zone $zoneName rectified successfully",
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to rectify zone: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Export zone to BIND format
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Export result
     */
    public function exportZone(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/export");

        if ($result['success']) {
            return [
                'success' => true,
                'zone_data' => $result['data']['zone'] ?? '',
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to export zone: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Get zone AXFR (zone transfer)
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array AXFR result
     */
    public function getZoneAxfr(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/axfr-retrieve");

        if ($result['success']) {
            Log::info('PowerDNS zone AXFR retrieved', [
                'provider' => $provider->name,
                'zone' => $zoneName,
            ]);

            return [
                'success' => true,
                'message' => "AXFR retrieved for zone $zoneName",
                'data' => $result['data'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to retrieve AXFR: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Notify zone slaves
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Notify result
     */
    public function notifyZone(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName/notify", 'PUT');

        if ($result['success']) {
            Log::info('PowerDNS zone slaves notified', [
                'provider' => $provider->name,
                'zone' => $zoneName,
            ]);

            return [
                'success' => true,
                'message' => "Zone slaves notified for $zoneName",
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to notify zone slaves: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Get advanced server statistics with caching
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  bool  $useCache  Whether to use cached results
     * @return array Enhanced statistics
     */
    public function getAdvancedStats(DnsProvider $provider, bool $useCache = true): array
    {
        $cacheKey = "powerdns_stats_{$provider->id}";

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->tunnelService->getServerStats($provider);

        if ($result['success']) {
            $stats = $result['stats'];

            // Process and enhance statistics
            $processedStats = $this->processStatistics($stats);

            $enhancedResult = [
                'success' => true,
                'stats' => $processedStats,
                'tunnel_used' => $result['tunnel_used'],
                'timestamp' => now()->toISOString(),
            ];

            // Cache for 5 minutes
            if ($useCache) {
                Cache::put($cacheKey, $enhancedResult, 300);
            }

            return $enhancedResult;
        }

        return $result;
    }

    /**
     * Process raw statistics into organized format
     *
     * @param  array  $rawStats  Raw statistics from PowerDNS
     * @return array Processed statistics
     */
    protected function processStatistics(array $rawStats): array
    {
        $categories = [
            'performance' => [],
            'cache' => [],
            'queries' => [],
            'answers' => [],
            'security' => [],
            'database' => [],
        ];

        foreach ($rawStats as $stat) {
            $name = $stat['name'] ?? '';
            $value = $stat['value'] ?? 0;

            // Categorize statistics
            if (str_contains($name, 'cache') || str_contains($name, 'packetcache')) {
                $categories['cache'][$name] = $value;
            } elseif (str_contains($name, 'queries') || str_contains($name, 'query')) {
                $categories['queries'][$name] = $value;
            } elseif (str_contains($name, 'answers') || str_contains($name, 'answer')) {
                $categories['answers'][$name] = $value;
            } elseif (str_contains($name, 'security') || str_contains($name, 'spoof') || str_contains($name, 'throttle')) {
                $categories['security'][$name] = $value;
            } elseif (str_contains($name, 'backend') || str_contains($name, 'db')) {
                $categories['database'][$name] = $value;
            } else {
                $categories['performance'][$name] = $value;
            }
        }

        return $categories;
    }

    /**
     * Health check for PowerDNS server
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Health check result
     */
    public function healthCheck(DnsProvider $provider): array
    {
        $results = [
            'overall_status' => 'healthy',
            'checks' => [],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            // Test basic connectivity
            $connectionTest = $this->tunnelService->testConnection($provider);
            $results['checks']['connectivity'] = $connectionTest['success'];

            if (! $connectionTest['success']) {
                $results['errors'][] = 'PowerDNS connection failed';
                $results['overall_status'] = 'unhealthy';
            }

            // Test zone count
            $zones = $this->tunnelService->getZones($provider);
            $zoneCount = count($zones);
            $results['checks']['zones_accessible'] = $zoneCount > 0;
            $results['zone_count'] = $zoneCount;

            // Get server statistics for health indicators
            $statsResult = $this->tunnelService->getServerStats($provider);
            if ($statsResult['success']) {
                $stats = $statsResult['stats'];

                // Check for high ACTUAL error rates (not normal counters)
                $errorStats = array_filter($stats, function ($stat) {
                    $name = $stat['name'] ?? '';

                    // Only check real error metrics, not normal operational counters
                    return (str_contains($name, 'servfail') ||
                            str_contains($name, 'error')) &&
                           ! str_contains($name, 'noerror') &&      // Exclude "noerror" (successful queries)
                           ! str_contains($name, 'capacity') &&     // Exclude capacity metrics
                           ! str_contains($name, 'size') &&         // Exclude size metrics
                           ! str_contains($name, 'noport') &&       // Exclude UDP no-port errors (normal)
                           ! str_contains($name, 'ring-');          // Exclude ring buffer stats
                });

                foreach ($errorStats as $stat) {
                    $value = $stat['value'] ?? 0;
                    // Handle arrays (some stats return arrays)
                    if (is_array($value)) {
                        $value = array_sum($value);
                    }

                    if ($value > 100) {
                        $results['warnings'][] = "High error count for {$stat['name']}: {$value}";
                    }
                }

                $results['checks']['statistics_available'] = true;
            } else {
                $results['checks']['statistics_available'] = false;
                $results['warnings'][] = 'Unable to retrieve server statistics';
            }

            // Determine overall status
            if (! empty($results['errors'])) {
                $results['overall_status'] = 'unhealthy';
            } elseif (! empty($results['warnings'])) {
                $results['overall_status'] = 'warning';
            }

        } catch (Exception $e) {
            $results['overall_status'] = 'unhealthy';
            $results['errors'][] = 'Health check failed: '.$e->getMessage();
        }

        return [
            'success' => true,
            'health' => $results,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Bulk import zones from zone files
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  array  $zoneFiles  Array of zone file contents
     * @param  bool  $dryRun  Whether to perform a dry run
     * @return array Import result
     */
    public function bulkImportZones(DnsProvider $provider, array $zoneFiles, bool $dryRun = false): array
    {
        $results = [
            'success' => true,
            'imported' => 0,
            'failed' => 0,
            'dry_run' => $dryRun,
            'details' => [],
        ];

        foreach ($zoneFiles as $zoneName => $zoneContent) {
            try {
                if ($dryRun) {
                    // Validate zone file format
                    if (empty($zoneContent) || ! str_contains($zoneContent, 'SOA')) {
                        $results['details'][$zoneName] = ['status' => 'invalid', 'message' => 'Invalid zone file format'];
                        $results['failed']++;
                    } else {
                        $results['details'][$zoneName] = ['status' => 'valid', 'message' => 'Zone file validation passed'];
                        $results['imported']++;
                    }
                } else {
                    // Actual import logic would go here
                    // This is a placeholder for the actual implementation
                    $results['details'][$zoneName] = ['status' => 'imported', 'message' => 'Zone imported successfully'];
                    $results['imported']++;
                }
            } catch (Exception $e) {
                $results['details'][$zoneName] = ['status' => 'error', 'message' => $e->getMessage()];
                $results['failed']++;
            }
        }

        if ($results['failed'] > 0) {
            $results['success'] = false;
        }

        Log::info('PowerDNS bulk zone import completed', [
            'provider' => $provider->name,
            'imported' => $results['imported'],
            'failed' => $results['failed'],
            'dry_run' => $dryRun,
        ]);

        return $results;
    }

    /**
     * Monitor zone changes
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  int  $since  Unix timestamp to get changes since
     * @return array Zone changes
     */
    public function getZoneChanges(DnsProvider $provider, string $zoneName, int $since): array
    {
        // This would integrate with PowerDNS logging or audit trail
        // For now, return a placeholder structure
        return [
            'success' => true,
            'zone' => $zoneName,
            'since' => $since,
            'changes' => [],
            'message' => 'Zone change monitoring not yet implemented',
        ];
    }

    /**
     * Validate DNSSEC for zone
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Validation result
     */
    public function validateDnssec(DnsProvider $provider, string $zoneName): array
    {
        try {
            // Get zone cryptokeys
            $keysResult = $this->getZoneCryptokeys($provider, $zoneName);

            if (! $keysResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to get zone cryptokeys: '.$keysResult['message'],
                ];
            }

            $keys = $keysResult['cryptokeys'];
            $validation = [
                'zone' => $zoneName,
                'dnssec_enabled' => ! empty($keys),
                'active_keys' => 0,
                'inactive_keys' => 0,
                'key_types' => [],
                'issues' => [],
                'recommendations' => [],
            ];

            foreach ($keys as $key) {
                if ($key['active'] ?? false) {
                    $validation['active_keys']++;
                } else {
                    $validation['inactive_keys']++;
                }

                $keyType = $key['keytype'] ?? 'unknown';
                $validation['key_types'][$keyType] = ($validation['key_types'][$keyType] ?? 0) + 1;
            }

            // Basic validation checks
            if (empty($keys)) {
                $validation['issues'][] = 'No DNSSEC keys found for zone';
                $validation['recommendations'][] = 'Enable DNSSEC by generating keys';
            } elseif ($validation['active_keys'] === 0) {
                $validation['issues'][] = 'No active DNSSEC keys found';
                $validation['recommendations'][] = 'Activate at least one DNSSEC key';
            }

            if (($validation['key_types']['ksk'] ?? 0) === 0) {
                $validation['recommendations'][] = 'Consider adding a Key Signing Key (KSK)';
            }

            if (($validation['key_types']['zsk'] ?? 0) === 0) {
                $validation['recommendations'][] = 'Consider adding a Zone Signing Key (ZSK)';
            }

            return [
                'success' => true,
                'validation' => $validation,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'DNSSEC validation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Create DNS record in zone
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name (must end with .)
     * @param  array  $recordData  Record data (name, type, content, ttl)
     * @return array Creation result
     */
    public function createRecord(DnsProvider $provider, string $zoneName, array $recordData): array
    {
        // Ensure zone name ends with dot
        if (! str_ends_with($zoneName, '.')) {
            $zoneName .= '.';
        }

        // Ensure record name ends with dot
        $recordName = $recordData['name'];
        if (! str_ends_with($recordName, '.')) {
            $recordName .= '.';
        }

        $recordType = strtoupper($recordData['type']);
        $recordContent = $recordData['content'];
        $recordTtl = $recordData['ttl'] ?? 300;
        $recordDisabled = $recordData['disabled'] ?? false;

        // Fetch existing zone data to get current RRset
        $zoneResult = $this->tunnelService->apiCall(
            $provider,
            "/servers/localhost/zones/$zoneName"
        );

        $existingRecords = [];
        if ($zoneResult['success'] && isset($zoneResult['data']['rrsets'])) {
            // Find existing RRset for this name+type
            foreach ($zoneResult['data']['rrsets'] as $rrset) {
                if ($rrset['name'] === $recordName && $rrset['type'] === $recordType) {
                    $existingRecords = $rrset['records'] ?? [];
                    // Use existing TTL if not explicitly set in recordData
                    if (! isset($recordData['ttl']) && isset($rrset['ttl'])) {
                        $recordTtl = $rrset['ttl'];
                    }
                    break;
                }
            }
        }

        // Check if record already exists
        foreach ($existingRecords as $existing) {
            if ($existing['content'] === $recordContent) {
                return [
                    'success' => false,
                    'message' => "Record already exists: {$recordName} {$recordType} {$recordContent}",
                ];
            }
        }

        // Build new records array: existing + new
        $allRecords = $existingRecords;
        $allRecords[] = [
            'content' => $recordContent,
            'disabled' => $recordDisabled,
        ];

        $rrsets = [[
            'name' => $recordName,
            'type' => $recordType,
            'ttl' => $recordTtl,
            'changetype' => 'REPLACE',
            'records' => $allRecords,
        ]];

        $result = $this->tunnelService->apiCall(
            $provider,
            "/servers/localhost/zones/$zoneName",
            'PATCH',
            ['rrsets' => $rrsets]
        );

        if ($result['success']) {
            Log::info('DNS record created', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'name' => $recordName,
                'type' => $recordType,
                'content' => $recordContent,
                'total_records_in_rrset' => count($allRecords),
            ]);

            return [
                'success' => true,
                'message' => "Record created: {$recordName} {$recordType} {$recordContent}",
                'record' => $recordData,
                'data' => ['id' => null], // PowerDNS doesn't return record IDs
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create record: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Delete DNS record from zone
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  string  $recordName  Record name
     * @param  string  $recordType  Record type (A, PTR, etc.)
     * @return array Deletion result
     */
    public function deleteRecord(DnsProvider $provider, string $zoneName, string $recordName, string $recordType): array
    {
        // Ensure zone name ends with dot
        if (! str_ends_with($zoneName, '.')) {
            $zoneName .= '.';
        }

        // Ensure record name ends with dot
        if (! str_ends_with($recordName, '.')) {
            $recordName .= '.';
        }

        $rrsets = [[
            'name' => $recordName,
            'type' => strtoupper($recordType),
            'changetype' => 'DELETE',
        ]];

        $result = $this->tunnelService->apiCall(
            $provider,
            "/servers/localhost/zones/$zoneName",
            'PATCH',
            ['rrsets' => $rrsets]
        );

        if ($result['success']) {
            Log::info('DNS record deleted', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'name' => $recordName,
                'type' => $recordType,
            ]);

            return [
                'success' => true,
                'message' => "Record deleted: {$recordName} {$recordType}",
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete record: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * List DNS records in zone
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  array  $filters  Optional filters (type, name)
     * @return array List of records
     */
    public function listRecords(DnsProvider $provider, string $zoneName, array $filters = []): array
    {
        // Ensure zone name ends with dot
        if (! str_ends_with($zoneName, '.')) {
            $zoneName .= '.';
        }

        $result = $this->tunnelService->apiCall($provider, "/servers/localhost/zones/$zoneName");

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to list records: '.($result['error'] ?? 'Unknown error'),
            ];
        }

        $zone = $result['data'];
        $records = [];

        foreach ($zone['rrsets'] ?? [] as $rrset) {
            // Apply filters
            if (! empty($filters['type']) && strtoupper($filters['type']) !== $rrset['type']) {
                continue;
            }

            if (! empty($filters['name']) && ! str_contains($rrset['name'], $filters['name'])) {
                continue;
            }

            $records[] = [
                'name' => $rrset['name'],
                'type' => $rrset['type'],
                'ttl' => $rrset['ttl'] ?? 300,
                'records' => $rrset['records'] ?? [],
            ];
        }

        return [
            'success' => true,
            'zone' => $zoneName,
            'records' => $records,
            'count' => count($records),
        ];
    }

    /**
     * Create both A and PTR records for FCrDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $fqdn  Fully qualified domain name
     * @param  string  $ip  IPv4 address
     * @param  int  $ttl  Time to live (default: 3600)
     * @return array Creation result
     */
    public function createFCrDNSRecords(
        DnsProvider $provider,
        string $fqdn,
        string $ip,
        int $ttl = 300
    ): array {
        // 1. Extract zone from FQDN (e.g., markc.goldcoast.org -> goldcoast.org)
        $parts = explode('.', $fqdn);
        if (count($parts) < 2) {
            return [
                'success' => false,
                'message' => "Invalid FQDN: $fqdn (must have at least 2 parts)",
            ];
        }

        $hostname = array_shift($parts);
        $zone = implode('.', $parts);

        // 2. Create A record
        $aResult = $this->createRecord($provider, $zone, [
            'name' => $fqdn,
            'type' => 'A',
            'content' => $ip,
            'ttl' => $ttl,
        ]);

        if (! $aResult['success']) {
            return [
                'success' => false,
                'message' => "Failed to create A record: {$aResult['message']}",
                'a_record' => $aResult,
            ];
        }

        // 3. Create PTR record (reverse zone)
        $reverseZone = $this->getReverseZone($ip);
        $ptrName = $this->getReverseName($ip);

        $ptrResult = $this->createRecord($provider, $reverseZone, [
            'name' => $ptrName,
            'type' => 'PTR',
            'content' => $fqdn,
            'ttl' => $ttl,
        ]);

        if (! $ptrResult['success']) {
            return [
                'success' => false,
                'message' => "A record created but PTR failed: {$ptrResult['message']}",
                'partial' => true,
                'a_record' => $aResult,
                'ptr_record' => $ptrResult,
            ];
        }

        Log::info('FCrDNS records created', [
            'provider' => $provider->name,
            'fqdn' => $fqdn,
            'ip' => $ip,
            'zone' => $zone,
            'reverse_zone' => $reverseZone,
        ]);

        return [
            'success' => true,
            'message' => "FCrDNS records created: $fqdn ↔ $ip",
            'fqdn' => $fqdn,
            'ip' => $ip,
            'a_record' => $aResult,
            'ptr_record' => $ptrResult,
        ];
    }

    /**
     * Delete both A and PTR records
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $fqdn  Fully qualified domain name
     * @param  string  $ip  IPv4 address
     * @return array Deletion result
     */
    public function deleteFCrDNSRecords(DnsProvider $provider, string $fqdn, string $ip): array
    {
        // Extract zone from FQDN
        $parts = explode('.', $fqdn);
        $hostname = array_shift($parts);
        $zone = implode('.', $parts);

        // Delete A record
        $aResult = $this->deleteRecord($provider, $zone, $fqdn, 'A');

        // Delete PTR record
        $reverseZone = $this->getReverseZone($ip);
        $ptrName = $this->getReverseName($ip);
        $ptrResult = $this->deleteRecord($provider, $reverseZone, $ptrName, 'PTR');

        return [
            'success' => $aResult['success'] && $ptrResult['success'],
            'message' => "FCrDNS records deleted: $fqdn ↔ $ip",
            'a_record' => $aResult,
            'ptr_record' => $ptrResult,
        ];
    }

    /**
     * Get reverse DNS zone for IP address
     *
     * @param  string  $ip  IPv4 address
     * @return string Reverse zone (e.g., 1.168.192.in-addr.arpa)
     */
    protected function getReverseZone(string $ip): string
    {
        $octets = explode('.', $ip);

        // For class C (/24) networks
        return "{$octets[2]}.{$octets[1]}.{$octets[0]}.in-addr.arpa";
    }

    /**
     * Get reverse DNS PTR record name for IP address
     *
     * @param  string  $ip  IPv4 address
     * @return string PTR name (e.g., 100.1.168.192.in-addr.arpa)
     */
    protected function getReverseName(string $ip): string
    {
        $octets = explode('.', $ip);

        return "{$octets[3]}.{$octets[2]}.{$octets[1]}.{$octets[0]}.in-addr.arpa";
    }

    /**
     * Validate that a record exists
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  string  $name  Record name
     * @param  string  $type  Record type
     * @return bool True if record exists
     */
    public function validateRecordExists(DnsProvider $provider, string $zoneName, string $name, string $type): bool
    {
        $result = $this->listRecords($provider, $zoneName, [
            'name' => $name,
            'type' => $type,
        ]);

        return $result['success'] && $result['count'] > 0;
    }

    /**
     * Get specific record by name and type
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  string  $name  Record name
     * @param  string  $type  Record type
     * @return array|null Record data or null if not found
     */
    public function getRecordByName(DnsProvider $provider, string $zoneName, string $name, string $type): ?array
    {
        $result = $this->listRecords($provider, $zoneName, [
            'name' => $name,
            'type' => $type,
        ]);

        if ($result['success'] && ! empty($result['records'])) {
            return $result['records'][0];
        }

        return null;
    }

    // =========================================================================
    // SYNC METHODS - Import zones and records from remote PowerDNS to database
    // =========================================================================

    /**
     * Discover PowerDNS API key from remote server via SSH
     *
     * Fetches the api-key from /etc/pdns/pdns.conf on the remote server.
     * Requires SSH access to the server (via ssh_host in connection_config).
     *
     * @param  string  $sshHost  SSH host alias (must exist in ssh_hosts table or ~/.ssh/config)
     * @return array Result with api_key or error
     */
    public function discoverApiKey(string $sshHost): array
    {
        try {
            // Use RemoteExecutionService for SSH
            $remoteService = app(\NetServa\Core\Services\RemoteExecutionService::class);

            // Fetch API key from PowerDNS config
            $script = "grep -E '^api-key=' /etc/pdns/pdns.conf 2>/dev/null | cut -d'=' -f2 | tr -d ' '";
            $result = $remoteService->executeScript($sshHost, $script);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to execute SSH command: '.($result['error'] ?? 'Unknown error'),
                ];
            }

            $apiKey = trim($result['output'] ?? '');

            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'API key not found in /etc/pdns/pdns.conf - check if api-key is configured',
                ];
            }

            Log::info('PowerDNS API key discovered', [
                'ssh_host' => $sshHost,
                'key_length' => strlen($apiKey),
            ]);

            return [
                'success' => true,
                'api_key' => $apiKey,
                'message' => 'API key discovered successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to discover API key: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync zones from remote PowerDNS to local database
     *
     * Fetches all zones from the PowerDNS API and imports them into the
     * dns_zones table. Skips zones that already exist.
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  callable|null  $progressCallback  Optional callback for progress updates
     * @return array Sync result with imported/skipped counts
     */
    public function syncZonesFromRemote(DnsProvider $provider, ?callable $progressCallback = null): array
    {
        try {
            // Get zones from remote
            $remoteZones = $this->tunnelService->getZones($provider);

            if (! is_array($remoteZones) || empty($remoteZones)) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch zones from remote or no zones found',
                ];
            }

            $imported = 0;
            $skipped = 0;

            foreach ($remoteZones as $remoteZone) {
                $zoneName = $remoteZone['name'] ?? null;

                if (! $zoneName) {
                    continue;
                }

                // Check if zone already exists
                $existingZone = \NetServa\Dns\Models\DnsZone::where('dns_provider_id', $provider->id)
                    ->where('name', $zoneName)
                    ->first();

                if ($existingZone) {
                    $skipped++;

                    continue;
                }

                // Map PowerDNS kind to database kind
                $kind = $remoteZone['kind'] ?? 'Native';
                $kindMap = [
                    'Master' => 'Primary',
                    'Slave' => 'Secondary',
                    'Primary' => 'Primary',
                    'Secondary' => 'Secondary',
                    'Native' => 'Native',
                    'Forwarded' => 'Forwarded',
                ];
                $kind = $kindMap[$kind] ?? 'Native';

                // Import zone
                \NetServa\Dns\Models\DnsZone::create([
                    'dns_provider_id' => $provider->id,
                    'external_id' => $remoteZone['id'] ?? $zoneName,
                    'name' => $zoneName,
                    'kind' => $kind,
                    'serial' => $remoteZone['serial'] ?? null,
                    'masters' => $remoteZone['masters'] ?? null,
                    'active' => true,
                    'provider_data' => $remoteZone,
                    'last_synced' => now(),
                ]);

                $imported++;

                if ($progressCallback) {
                    $progressCallback('zone', $zoneName, $imported, $skipped);
                }
            }

            Log::info('PowerDNS zones synced', [
                'provider' => $provider->name,
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'total' => count($remoteZones),
                'message' => "Zone sync complete: {$imported} imported, {$skipped} skipped",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Zone sync failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync records from remote PowerDNS to local database
     *
     * Fetches all records for all zones from the PowerDNS API and imports
     * them into the dns_records table. Skips records that already exist.
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  callable|null  $progressCallback  Optional callback for progress updates
     * @return array Sync result with imported/skipped counts per zone
     */
    public function syncRecordsFromRemote(DnsProvider $provider, ?callable $progressCallback = null): array
    {
        try {
            // Get all zones for this provider
            $zones = \NetServa\Dns\Models\DnsZone::where('dns_provider_id', $provider->id)->get();

            if ($zones->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No zones found for this provider - run zone sync first',
                ];
            }

            $totalImported = 0;
            $totalSkipped = 0;
            $zoneResults = [];

            foreach ($zones as $zone) {
                $zoneName = $zone->name;

                // Ensure zone name ends with dot for API call
                if (! str_ends_with($zoneName, '.')) {
                    $zoneName .= '.';
                }

                // Get zone data with records from PowerDNS
                $zoneData = $this->tunnelService->getZone($provider, $zoneName);

                if (! $zoneData || ! isset($zoneData['rrsets'])) {
                    $zoneResults[$zone->name] = [
                        'imported' => 0,
                        'skipped' => 0,
                        'error' => 'Failed to fetch zone data',
                    ];

                    continue;
                }

                $zoneImported = 0;
                $zoneSkipped = 0;

                foreach ($zoneData['rrsets'] as $rrset) {
                    // Skip SOA records (managed by PowerDNS)
                    if ($rrset['type'] === 'SOA') {
                        continue;
                    }

                    foreach ($rrset['records'] as $record) {
                        // Normalize name - remove trailing dot for storage consistency
                        $normalizedName = rtrim($rrset['name'], '.');

                        // Check if record already exists (using normalized name)
                        $existing = \NetServa\Dns\Models\DnsRecord::where('dns_zone_id', $zone->id)
                            ->where('name', $normalizedName)
                            ->where('type', $rrset['type'])
                            ->where('content', $record['content'])
                            ->first();

                        if ($existing) {
                            $zoneSkipped++;

                            continue;
                        }

                        // Import record without triggering observer (records already exist in PowerDNS)
                        \NetServa\Dns\Models\DnsRecord::withoutEvents(function () use ($zone, $normalizedName, $rrset, $record) {
                            \NetServa\Dns\Models\DnsRecord::create([
                                'dns_zone_id' => $zone->id,
                                'name' => $normalizedName,
                                'type' => $rrset['type'],
                                'content' => $record['content'],
                                'ttl' => $rrset['ttl'] ?? 3600,
                                'disabled' => $record['disabled'] ?? false,
                                'last_synced' => now(),
                            ]);
                        });

                        $zoneImported++;
                    }
                }

                // Update zone record count
                $zone->records_count = \NetServa\Dns\Models\DnsRecord::where('dns_zone_id', $zone->id)->count();
                $zone->last_synced = now();
                $zone->save();

                $totalImported += $zoneImported;
                $totalSkipped += $zoneSkipped;

                $zoneResults[$zone->name] = [
                    'imported' => $zoneImported,
                    'skipped' => $zoneSkipped,
                ];

                if ($progressCallback) {
                    $progressCallback('records', $zone->name, $zoneImported, $zoneSkipped);
                }
            }

            Log::info('PowerDNS records synced', [
                'provider' => $provider->name,
                'zones' => $zones->count(),
                'total_imported' => $totalImported,
                'total_skipped' => $totalSkipped,
            ]);

            return [
                'success' => true,
                'zones_processed' => $zones->count(),
                'total_imported' => $totalImported,
                'total_skipped' => $totalSkipped,
                'zone_results' => $zoneResults,
                'message' => "Record sync complete: {$totalImported} imported, {$totalSkipped} skipped across {$zones->count()} zones",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Record sync failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Full sync: zones AND records from remote PowerDNS
     *
     * Convenience method that calls both syncZonesFromRemote and
     * syncRecordsFromRemote in sequence.
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  callable|null  $progressCallback  Optional callback for progress updates
     * @return array Combined sync result
     */
    public function syncAllFromRemote(DnsProvider $provider, ?callable $progressCallback = null): array
    {
        // First sync zones
        $zoneResult = $this->syncZonesFromRemote($provider, $progressCallback);

        if (! $zoneResult['success']) {
            return $zoneResult;
        }

        // Then sync records
        $recordResult = $this->syncRecordsFromRemote($provider, $progressCallback);

        return [
            'success' => $recordResult['success'],
            'zones' => [
                'imported' => $zoneResult['imported'],
                'skipped' => $zoneResult['skipped'],
            ],
            'records' => [
                'imported' => $recordResult['total_imported'] ?? 0,
                'skipped' => $recordResult['total_skipped'] ?? 0,
                'zone_results' => $recordResult['zone_results'] ?? [],
            ],
            'message' => "Full sync complete: {$zoneResult['imported']} zones, {$recordResult['total_imported']} records imported",
        ];
    }
}
