<?php

namespace NetServa\Dns\Services;

use Illuminate\Support\Collection;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;

/**
 * DNS Provider Management Service
 *
 * Handles CRUD operations for DNS providers with connection testing,
 * health monitoring, and usage tracking across the Fleet hierarchy.
 *
 * Shared by both CLI commands and Filament UI.
 */
class DnsProviderManagementService
{
    public function __construct(
        protected PowerDnsService $powerDnsService,
    ) {}

    /**
     * Create a new DNS provider
     *
     * @param  string  $name  Provider name (e.g., "Homelab PowerDNS")
     * @param  string  $type  Provider type (powerdns, cloudflare, route53, etc.)
     * @param  array  $connectionConfig  Connection configuration
     * @param  array  $options  Additional options (version, priority, active, etc.)
     * @return array Result with success status and provider data
     */
    public function createProvider(
        string $name,
        string $type,
        array $connectionConfig,
        array $options = []
    ): array {
        try {
            // Create provider
            $provider = DnsProvider::create([
                'name' => $name,
                'type' => $type,
                'description' => $options['description'] ?? null,
                'active' => $options['active'] ?? true,
                'version' => $options['version'] ?? null,
                'connection_config' => $connectionConfig,
                'sync_config' => $options['sync_config'] ?? [],
                'rate_limit' => $options['rate_limit'] ?? 100,
                'timeout' => $options['timeout'] ?? 30,
                'sort_order' => $options['sort_order'] ?? 0,
            ]);

            // Test connection if requested
            $connectionTest = null;
            if ($options['test_connection'] ?? true) {
                $connectionTest = $this->testProviderConnection($provider);
            }

            return [
                'success' => true,
                'provider' => $provider,
                'connection_test' => $connectionTest,
                'message' => "DNS Provider '{$name}' created successfully",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Failed to create DNS provider: {$e->getMessage()}",
            ];
        }
    }

    /**
     * List DNS providers with optional filtering
     *
     * @param  array  $filters  Filter criteria
     * @return Collection
     */
    public function listProviders(array $filters = []): Collection
    {
        $query = DnsProvider::query();

        // Filter by type
        if (isset($filters['type'])) {
            if (is_array($filters['type'])) {
                $query->whereIn('type', $filters['type']);
            } else {
                $query->where('type', $filters['type']);
            }
        }

        // Filter by active status
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        // Filter by name (search)
        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        // Load relationships if requested
        $with = [];
        if ($filters['with_zones'] ?? false) {
            $with[] = 'zones';
        }
        if ($filters['with_usage'] ?? false) {
            $with = array_merge($with, ['venues', 'vsites', 'vnodes', 'vhosts']);
        }

        if (! empty($with)) {
            $query->with($with);
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->get();
    }

    /**
     * Show detailed information about a DNS provider
     *
     * @param  int|string  $identifier  Provider ID or name
     * @param  array  $options  Display options
     * @return array Result with provider data
     */
    public function showProvider(int|string $identifier, array $options = []): array
    {
        try {
            $provider = $this->findProvider($identifier);

            if (! $provider) {
                return [
                    'success' => false,
                    'message' => "DNS provider not found: {$identifier}",
                ];
            }

            $data = [
                'success' => true,
                'provider' => $provider,
                'zones_count' => $provider->zones()->count(),
            ];

            // Load usage statistics if requested
            if ($options['with_usage'] ?? false) {
                $data['usage'] = [
                    'venues' => $provider->venues()->count(),
                    'vsites' => $provider->vsites()->count(),
                    'vnodes' => $provider->vnodes()->count(),
                    'vhosts' => $provider->vhosts()->count(),
                ];
            }

            // Load zone details if requested
            if ($options['with_zones'] ?? false) {
                $data['zones'] = $provider->zones()
                    ->with('records')
                    ->get()
                    ->map(fn ($zone) => [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'active' => $zone->active,
                        'records_count' => $zone->records->count(),
                    ]);
            }

            // Test connection if requested
            if ($options['test_connection'] ?? false) {
                $data['connection_test'] = $this->testProviderConnection($provider);
            }

            // Get health status if requested
            if ($options['with_health'] ?? false) {
                $data['health'] = $this->getProviderHealth($provider);
            }

            return $data;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Failed to show DNS provider: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Update DNS provider configuration
     *
     * @param  int|string  $identifier  Provider ID or name
     * @param  array  $updates  Fields to update
     * @param  array  $options  Update options
     * @return array Result with success status
     */
    public function updateProvider(int|string $identifier, array $updates, array $options = []): array
    {
        try {
            $provider = $this->findProvider($identifier);

            if (! $provider) {
                return [
                    'success' => false,
                    'message' => "DNS provider not found: {$identifier}",
                ];
            }

            // Track changes for output
            $changes = [];

            // Update basic fields
            $basicFields = ['name', 'description', 'active', 'version', 'rate_limit', 'timeout', 'sort_order'];
            foreach ($basicFields as $field) {
                if (isset($updates[$field]) && $provider->{$field} !== $updates[$field]) {
                    $changes[$field] = [
                        'old' => $provider->{$field},
                        'new' => $updates[$field],
                    ];
                    $provider->{$field} = $updates[$field];
                }
            }

            // Update connection config (merge with existing)
            if (isset($updates['connection_config'])) {
                $oldConfig = $provider->connection_config ?? [];
                $newConfig = array_merge($oldConfig, $updates['connection_config']);

                if ($oldConfig !== $newConfig) {
                    $changes['connection_config'] = [
                        'old' => $oldConfig,
                        'new' => $newConfig,
                    ];
                    $provider->connection_config = $newConfig;
                }
            }

            // Update sync config (merge with existing)
            if (isset($updates['sync_config'])) {
                $oldSyncConfig = $provider->sync_config ?? [];
                $newSyncConfig = array_merge($oldSyncConfig, $updates['sync_config']);

                if ($oldSyncConfig !== $newSyncConfig) {
                    $changes['sync_config'] = [
                        'old' => $oldSyncConfig,
                        'new' => $newSyncConfig,
                    ];
                    $provider->sync_config = $newSyncConfig;
                }
            }

            // Save changes
            if (! empty($changes)) {
                $provider->save();
            }

            // Test connection if requested
            $connectionTest = null;
            if ($options['test_connection'] ?? false) {
                $connectionTest = $this->testProviderConnection($provider);
            }

            return [
                'success' => true,
                'provider' => $provider->fresh(),
                'changes' => $changes,
                'connection_test' => $connectionTest,
                'message' => empty($changes)
                    ? 'No changes made'
                    : "DNS Provider '{$provider->name}' updated successfully",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Failed to update DNS provider: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Delete DNS provider
     *
     * @param  int|string  $identifier  Provider ID or name
     * @param  array  $options  Delete options (force, cascade, reassign)
     * @return array Result with success status
     */
    public function deleteProvider(int|string $identifier, array $options = []): array
    {
        try {
            $provider = $this->findProvider($identifier);

            if (! $provider) {
                return [
                    'success' => false,
                    'message' => "DNS provider not found: {$identifier}",
                ];
            }

            // Check for zones
            $zonesCount = $provider->zones()->count();
            $hasZones = $zonesCount > 0;

            // Check for usage in Fleet hierarchy
            $usage = [
                'venues' => $provider->venues()->count(),
                'vsites' => $provider->vsites()->count(),
                'vnodes' => $provider->vnodes()->count(),
                'vhosts' => $provider->vhosts()->count(),
            ];
            $inUse = array_sum($usage) > 0;

            // Prevent deletion if zones exist or in use (unless forced)
            if (($hasZones || $inUse) && ! ($options['force'] ?? false)) {
                return [
                    'success' => false,
                    'provider' => $provider,
                    'zones_count' => $zonesCount,
                    'usage' => $usage,
                    'message' => 'Cannot delete DNS provider - zones exist or provider is in use',
                ];
            }

            // Handle cascade delete (delete all zones and records)
            if ($options['cascade'] ?? false) {
                foreach ($provider->zones as $zone) {
                    // Delete all records in zone
                    $zone->records()->delete();
                    // Delete zone
                    $zone->delete();
                }
            }

            // Handle reassignment (move zones to another provider)
            if (isset($options['reassign'])) {
                $targetProvider = $this->findProvider($options['reassign']);

                if (! $targetProvider) {
                    return [
                        'success' => false,
                        'message' => "Target provider not found: {$options['reassign']}",
                    ];
                }

                $provider->zones()->update(['dns_provider_id' => $targetProvider->id]);
            }

            // Delete the provider
            $providerName = $provider->name;
            $provider->delete();

            return [
                'success' => true,
                'message' => "DNS Provider '{$providerName}' deleted successfully",
                'zones_deleted' => $options['cascade'] ?? false ? $zonesCount : 0,
                'zones_reassigned' => isset($options['reassign']) ? $zonesCount : 0,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Failed to delete DNS provider: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Test connection to DNS provider
     *
     * @param  DnsProvider  $provider
     * @return array Connection test result
     */
    public function testProviderConnection(DnsProvider $provider): array
    {
        try {
            $startTime = microtime(true);

            $result = match ($provider->type) {
                'powerdns' => $this->powerDnsService->testConnection($provider),
                'cloudflare' => $this->testCloudflareConnection($provider),
                'route53' => $this->testRoute53Connection($provider),
                default => [
                    'success' => false,
                    'message' => "Connection test not implemented for provider type: {$provider->type}",
                ],
            };

            $latency = round((microtime(true) - $startTime) * 1000); // ms

            return array_merge($result, [
                'latency_ms' => $latency,
                'tested_at' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Connection test failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Get provider health status
     *
     * @param  DnsProvider  $provider
     * @return array Health status data
     */
    public function getProviderHealth(DnsProvider $provider): array
    {
        return match ($provider->type) {
            'powerdns' => $this->powerDnsService->healthCheck($provider),
            default => [
                'success' => false,
                'message' => "Health check not implemented for provider type: {$provider->type}",
            ],
        };
    }

    /**
     * Find provider by ID or name
     *
     * @param  int|string  $identifier
     * @return DnsProvider|null
     */
    protected function findProvider(int|string $identifier): ?DnsProvider
    {
        if (is_numeric($identifier)) {
            return DnsProvider::find($identifier);
        }

        return DnsProvider::where('name', $identifier)->first();
    }

    /**
     * Test Cloudflare connection
     *
     * @param  DnsProvider  $provider
     * @return array
     */
    protected function testCloudflareConnection(DnsProvider $provider): array
    {
        try {
            $client = $provider->getClient();

            if ($client->testConnection()) {
                // Get zones count as additional info
                $zones = $client->getAllZones();
                $zonesCount = is_array($zones) ? count($zones) : 0;

                return [
                    'success' => true,
                    'message' => 'Connected to Cloudflare API successfully',
                    'server_info' => 'Cloudflare API',
                    'zones_count' => $zonesCount,
                ];
            }

            return [
                'success' => false,
                'message' => 'Cloudflare API authentication failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Cloudflare connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test Route53 connection (stub - to be implemented)
     *
     * @param  DnsProvider  $provider
     * @return array
     */
    protected function testRoute53Connection(DnsProvider $provider): array
    {
        // TODO: Implement AWS Route53 connection test
        return [
            'success' => false,
            'message' => 'Route53 connection test not yet implemented',
        ];
    }

    /**
     * Get usage summary for a provider
     *
     * @param  DnsProvider  $provider
     * @return array Usage statistics
     */
    public function getUsageSummary(DnsProvider $provider): array
    {
        return [
            'zones' => $provider->zones()->count(),
            'records' => DnsZone::whereHas('zones', function ($query) use ($provider) {
                $query->where('dns_provider_id', $provider->id);
            })->count(),
            'venues' => $provider->venues()->count(),
            'vsites' => $provider->vsites()->count(),
            'vnodes' => $provider->vnodes()->count(),
            'vhosts' => $provider->vhosts()->count(),
        ];
    }
}
