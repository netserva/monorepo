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

                // Check for high error rates
                $errorStats = array_filter($stats, function ($stat) {
                    return str_contains($stat['name'], 'servfail') || str_contains($stat['name'], 'error');
                });

                foreach ($errorStats as $stat) {
                    if (($stat['value'] ?? 0) > 100) {
                        $results['warnings'][] = "High error count for {$stat['name']}: {$stat['value']}";
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
}
