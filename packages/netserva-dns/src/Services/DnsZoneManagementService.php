<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

/**
 * DNS Zone Management Service
 *
 * Provides complete CRUD operations for DNS zones
 * Shared service layer between CLI commands and Filament UI
 *
 * Database-First Architecture:
 * - All zone data stored in dns_zones table
 * - Write-through cache pattern (write to remote first, then local)
 * - Sync operations pull from remote to update local cache
 *
 * Tier 2 in DNS Hierarchy:
 * - Provider → Zone → Record
 * - Each zone belongs to one provider
 * - Each zone can have many records
 */
class DnsZoneManagementService
{
    public function __construct(
        protected PowerDnsTunnelService $tunnelService,
        protected PowerDnsService $powerDnsService
    ) {}

    /**
     * Create a new DNS zone
     *
     * @param  string  $zoneName  Zone name (e.g., "example.com")
     * @param  int|string  $providerId  Provider ID or name
     * @param  array  $options  Additional options
     * @return array Result with success status and zone data
     */
    public function createZone(string $zoneName, int|string $providerId, array $options = []): array
    {
        try {
            // Find provider
            $providerResult = $this->findProvider($providerId);
            if (! $providerResult['success']) {
                return $providerResult;
            }

            $provider = $providerResult['provider'];

            // Check if zone already exists
            $existingZone = DnsZone::where('name', $zoneName)
                ->where('dns_provider_id', $provider->id)
                ->first();

            if ($existingZone) {
                return [
                    'success' => false,
                    'message' => "Zone '{$zoneName}' already exists on provider '{$provider->name}'",
                    'zone' => $existingZone,
                ];
            }

            // Prepare zone data for remote creation
            $zoneData = [
                'name' => rtrim($zoneName, '.').'.', // Ensure trailing dot
                'kind' => $options['kind'] ?? 'Native',
                'nameservers' => $options['nameservers'] ?? [],
                'soa_edit_api' => $options['soa_edit_api'] ?? 'DEFAULT',
            ];

            // Add masters if Secondary zone
            if (isset($options['masters']) && ($options['kind'] ?? 'Native') === 'Secondary') {
                $zoneData['masters'] = is_array($options['masters'])
                    ? $options['masters']
                    : explode(',', $options['masters']);
            }

            // Test connection first (unless skipped)
            if (! ($options['skip_test'] ?? false)) {
                $connectionTest = $this->testProviderConnection($provider);
                if (! $connectionTest['success']) {
                    return [
                        'success' => false,
                        'message' => "Cannot create zone - provider connection failed: {$connectionTest['message']}",
                    ];
                }
            }

            DB::beginTransaction();

            try {
                // Create zone on remote provider FIRST (write-through cache)
                $remoteResult = $this->createZoneOnRemote($provider, $zoneData);

                if (! $remoteResult['success']) {
                    throw new Exception($remoteResult['message']);
                }

                // Create zone in local database
                $zone = DnsZone::create([
                    'dns_provider_id' => $provider->id,
                    'external_id' => $remoteResult['data']['id'] ?? $zoneName,
                    'name' => $zoneName,
                    'kind' => $options['kind'] ?? 'Native',
                    'masters' => $options['masters'] ?? null,
                    'ttl' => $options['ttl'] ?? 300,
                    'active' => $options['active'] ?? true,
                    'description' => $options['description'] ?? null,
                    'auto_dnssec' => $options['auto_dnssec'] ?? false,
                    'dnssec_enabled' => false,
                    'nameservers' => $options['nameservers'] ?? [],
                    'sort_order' => $options['sort_order'] ?? 0,
                    'provider_data' => $remoteResult['data'] ?? [],
                    'last_synced' => now(),
                ]);

                // Auto-create SOA and NS records if requested
                if ($options['create_default_records'] ?? true) {
                    $this->createDefaultRecords($zone, $provider, $options);
                }

                // Enable DNSSEC if requested
                if ($options['auto_dnssec'] ?? false) {
                    $dnssecResult = $this->powerDnsService->generateDnssecKey($provider, $zoneName);
                    if ($dnssecResult['success']) {
                        $zone->update(['dnssec_enabled' => true]);
                    }
                }

                DB::commit();

                $result = [
                    'success' => true,
                    'message' => "Zone '{$zoneName}' created successfully",
                    'zone' => $zone->fresh(),
                    'remote_result' => $remoteResult,
                ];

                // Add DNSSEC info if enabled
                if ($options['auto_dnssec'] ?? false) {
                    $result['dnssec'] = $dnssecResult ?? null;
                }

                return $result;

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Zone creation failed', [
                    'zone' => $zoneName,
                    'provider' => $provider->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create zone: '.$e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Zone creation error: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List DNS zones with optional filtering
     *
     * @param  array  $filters  Filter criteria
     */
    public function listZones(array $filters = []): Collection
    {
        $query = DnsZone::query()
            ->with(['dnsProvider', 'records'])
            ->orderBy('sort_order')
            ->orderBy('name');

        // Filter by provider
        if (isset($filters['provider'])) {
            $providerResult = $this->findProvider($filters['provider']);
            if ($providerResult['success']) {
                $query->where('dns_provider_id', $providerResult['provider']->id);
            }
        }

        // Filter by type/kind
        if (isset($filters['type'])) {
            $query->where('kind', $filters['type']);
        }

        // Filter by active status
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        // Filter by DNSSEC status
        if (isset($filters['dnssec'])) {
            $query->where('dnssec_enabled', $filters['dnssec']);
        }

        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->get();
    }

    /**
     * Show detailed information about a DNS zone
     *
     * @param  int|string  $identifier  Zone ID or name
     * @param  array  $options  Display options
     * @return array Result with zone data
     */
    public function showZone(int|string $identifier, array $options = []): array
    {
        try {
            $zoneResult = $this->findZone($identifier);
            if (! $zoneResult['success']) {
                return $zoneResult;
            }

            $zone = $zoneResult['zone'];
            $provider = $zone->dnsProvider;

            $result = [
                'success' => true,
                'zone' => $zone->load(['dnsProvider', 'records']),
                'provider' => $provider,
            ];

            // Include record count
            if ($options['with_records'] ?? false) {
                $result['records_count'] = $zone->records()->count();
                $result['active_records_count'] = $zone->records()->where('disabled', false)->count();
            }

            // Include DNSSEC information
            if ($options['with_dnssec'] ?? false) {
                $dnssecStatus = $this->powerDnsService->getZoneCryptokeys($provider, $zone->name);
                $result['dnssec_status'] = $dnssecStatus;
            }

            // Include zone metadata
            if ($options['with_metadata'] ?? false) {
                $metadata = $this->powerDnsService->getZoneMetadata($provider, $zone->name);
                $result['metadata'] = $metadata;
            }

            // Sync from remote if requested
            if ($options['sync'] ?? false) {
                $syncResult = $this->syncZoneFromRemote($zone);
                $result['sync_result'] = $syncResult;
                $result['zone'] = $zone->fresh();
            }

            // Test connection if requested
            if ($options['test'] ?? false) {
                $result['connection_test'] = $this->testProviderConnection($provider);
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to show zone: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update DNS zone configuration
     *
     * @param  int|string  $identifier  Zone ID or name
     * @param  array  $updates  Fields to update
     * @param  array  $options  Update options
     * @return array Result with updated zone
     */
    public function updateZone(int|string $identifier, array $updates, array $options = []): array
    {
        try {
            $zoneResult = $this->findZone($identifier);
            if (! $zoneResult['success']) {
                return $zoneResult;
            }

            $zone = $zoneResult['zone'];
            $provider = $zone->dnsProvider;
            $changes = [];

            DB::beginTransaction();

            try {
                // Track changes
                foreach ($updates as $field => $value) {
                    if (isset($zone->$field) && $value !== $zone->$field) {
                        $changes[$field] = [
                            'old' => $zone->$field,
                            'new' => $value,
                        ];
                    }
                }

                // Update on remote first if critical fields changed
                $remoteFields = ['kind', 'masters', 'ttl', 'dnssec_enabled'];
                $needsRemoteUpdate = ! empty(array_intersect(array_keys($updates), $remoteFields));

                if ($needsRemoteUpdate) {
                    $remoteData = [];

                    if (isset($updates['kind'])) {
                        $remoteData['kind'] = $updates['kind'];
                    }
                    if (isset($updates['masters'])) {
                        $remoteData['masters'] = is_array($updates['masters'])
                            ? $updates['masters']
                            : explode(',', $updates['masters']);
                    }

                    if (! empty($remoteData)) {
                        $remoteResult = $this->updateZoneOnRemote($provider, $zone->name, $remoteData);
                        if (! $remoteResult['success']) {
                            throw new Exception($remoteResult['message']);
                        }
                    }

                    // Handle DNSSEC separately
                    if (isset($updates['dnssec_enabled'])) {
                        if ($updates['dnssec_enabled'] && ! $zone->dnssec_enabled) {
                            $dnssecResult = $this->powerDnsService->generateDnssecKey($provider, $zone->name);
                            if (! $dnssecResult['success']) {
                                throw new Exception('Failed to enable DNSSEC: '.$dnssecResult['message']);
                            }
                        } elseif (! $updates['dnssec_enabled'] && $zone->dnssec_enabled) {
                            // Disable DNSSEC logic here if needed
                        }
                    }
                }

                // Update local database
                $zone->update($updates);

                DB::commit();

                $result = [
                    'success' => true,
                    'message' => "Zone '{$zone->name}' updated successfully",
                    'zone' => $zone->fresh(),
                    'changes' => $changes,
                ];

                // Test connection if requested
                if ($options['test'] ?? false) {
                    $result['connection_test'] = $this->testProviderConnection($provider);
                }

                return $result;

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Zone update failed', [
                    'zone' => $zone->id,
                    'updates' => $updates,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to update zone: '.$e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Zone update error: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete DNS zone
     *
     * @param  int|string  $identifier  Zone ID or name
     * @param  array  $options  Deletion options
     * @return array Result with deletion status
     */
    public function deleteZone(int|string $identifier, array $options = []): array
    {
        try {
            $zoneResult = $this->findZone($identifier);
            if (! $zoneResult['success']) {
                return $zoneResult;
            }

            $zone = $zoneResult['zone'];
            $provider = $zone->dnsProvider;

            // Check for records
            $recordsCount = $zone->records()->count();
            $hasRecords = $recordsCount > 0;

            // Prevent deletion if has records (unless cascade or force)
            if ($hasRecords && ! ($options['cascade'] ?? false) && ! ($options['force'] ?? false)) {
                return [
                    'success' => false,
                    'message' => "Cannot delete zone '{$zone->name}' - it has {$recordsCount} record(s)",
                    'hint' => 'Use --cascade to delete all records, or --force to override',
                    'zone' => $zone,
                    'records_count' => $recordsCount,
                ];
            }

            DB::beginTransaction();

            try {
                // Delete from remote first (unless skip-remote)
                if (! ($options['skip_remote'] ?? false)) {
                    $remoteResult = $this->deleteZoneOnRemote($provider, $zone->name);
                    if (! $remoteResult['success'] && ! ($options['force'] ?? false)) {
                        throw new Exception($remoteResult['message']);
                    }
                }

                // Delete all records if cascade
                if ($options['cascade'] ?? false) {
                    $zone->records()->delete();
                }

                // Soft delete the zone
                $zoneName = $zone->name;
                $zone->delete();

                DB::commit();

                return [
                    'success' => true,
                    'message' => "Zone '{$zoneName}' deleted successfully",
                    'zone' => $zone,
                    'records_deleted' => $options['cascade'] ? $recordsCount : 0,
                ];

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Zone deletion failed', [
                    'zone' => $zone->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to delete zone: '.$e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Zone deletion error: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync zone data from remote provider
     *
     * @return array Sync result
     */
    public function syncZoneFromRemote(DnsZone $zone): array
    {
        try {
            $provider = $zone->dnsProvider;

            // Get zone from remote (returns raw data, not structured response)
            $remoteZone = $this->tunnelService->getZone($provider, $zone->name);

            if (empty($remoteZone)) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch zone from remote - empty response',
                ];
            }

            // Update local zone with remote data
            $zone->update([
                'serial' => $remoteZone['serial'] ?? $zone->serial,
                'kind' => $remoteZone['kind'] ?? $zone->kind,
                'masters' => $remoteZone['masters'] ?? $zone->masters,
                'last_check' => now(),
                'last_synced' => now(),
                'provider_data' => $remoteZone,
            ]);

            // Sync records if requested
            $recordsSynced = 0;
            if ($remoteZone['rrsets'] ?? false) {
                foreach ($remoteZone['rrsets'] as $rrset) {
                    $recordName = $rrset['name'];
                    $zoneName = rtrim($zone->name, '.');

                    // Only import records that belong to this zone
                    // Skip records from other zones (e.g., glue records)
                    if (! str_ends_with(rtrim($recordName, '.'), $zoneName)) {
                        continue;
                    }

                    foreach ($rrset['records'] ?? [] as $record) {
                        DnsRecord::updateOrCreate(
                            [
                                'dns_zone_id' => $zone->id,
                                'name' => $rrset['name'],
                                'type' => $rrset['type'],
                                'content' => $record['content'],
                            ],
                            [
                                'ttl' => $rrset['ttl'] ?? 300,
                                'disabled' => $record['disabled'] ?? false,
                                'last_synced' => now(),
                            ]
                        );
                        $recordsSynced++;
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Zone synced successfully',
                'zone' => $zone->fresh(),
                'records_synced' => $recordsSynced,
            ];

        } catch (Exception $e) {
            Log::error('Zone sync failed', [
                'zone' => $zone->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find provider by ID or name
     */
    protected function findProvider(int|string $identifier): array
    {
        $provider = is_numeric($identifier)
            ? DnsProvider::find($identifier)
            : DnsProvider::where('name', $identifier)->first();

        if (! $provider) {
            return [
                'success' => false,
                'message' => is_numeric($identifier)
                    ? "Provider ID {$identifier} not found"
                    : "Provider '{$identifier}' not found",
            ];
        }

        return [
            'success' => true,
            'provider' => $provider,
        ];
    }

    /**
     * Find zone by ID or name
     *
     * Supports lookups with or without trailing dot:
     * - "example.com" matches "example.com."
     * - "example.com." matches "example.com."
     */
    protected function findZone(int|string $identifier): array
    {
        if (is_numeric($identifier)) {
            $zone = DnsZone::find($identifier);
        } else {
            // Normalize: ensure trailing dot for database lookup
            $normalizedName = rtrim($identifier, '.').'.';
            $zone = DnsZone::where('name', $normalizedName)->first();
        }

        if (! $zone) {
            return [
                'success' => false,
                'message' => is_numeric($identifier)
                    ? "Zone ID {$identifier} not found"
                    : "Zone '{$identifier}' not found",
            ];
        }

        return [
            'success' => true,
            'zone' => $zone,
        ];
    }

    /**
     * Test provider connection
     */
    protected function testProviderConnection(DnsProvider $provider): array
    {
        return match ($provider->type) {
            'powerdns' => $this->powerDnsService->testConnection($provider),
            default => [
                'success' => false,
                'message' => 'Connection test not implemented for '.$provider->type,
            ],
        };
    }

    /**
     * Create zone on remote provider
     */
    protected function createZoneOnRemote(DnsProvider $provider, array $zoneData): array
    {
        try {
            return match ($provider->type) {
                'powerdns' => $this->tunnelService->createZone($provider, $zoneData),
                default => [
                    'success' => false,
                    'message' => 'Zone creation not implemented for '.$provider->type,
                ],
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote zone creation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update zone on remote provider
     */
    protected function updateZoneOnRemote(DnsProvider $provider, string $zoneName, array $updateData): array
    {
        try {
            return match ($provider->type) {
                'powerdns' => $this->tunnelService->updateZone($provider, $zoneName, $updateData),
                default => [
                    'success' => false,
                    'message' => 'Zone update not implemented for '.$provider->type,
                ],
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote zone update failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Delete zone on remote provider
     */
    protected function deleteZoneOnRemote(DnsProvider $provider, string $zoneName): array
    {
        try {
            return match ($provider->type) {
                'powerdns' => $this->tunnelService->deleteZone($provider, $zoneName),
                default => [
                    'success' => false,
                    'message' => 'Zone deletion not implemented for '.$provider->type,
                ],
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote zone deletion failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Create default SOA and NS records for new zone
     */
    protected function createDefaultRecords(DnsZone $zone, DnsProvider $provider, array $options): void
    {
        $nameservers = $options['nameservers'] ?? ['ns1.example.com.', 'ns2.example.com.'];
        $adminEmail = $options['admin_email'] ?? 'admin@example.com';

        // Create SOA record
        $soaContent = sprintf(
            '%s %s %d 10800 3600 604800 3600',
            $nameservers[0],
            str_replace('@', '.', $adminEmail),
            $zone->getNextSerial()
        );

        DnsRecord::create([
            'dns_zone_id' => $zone->id,
            'name' => $zone->name,
            'type' => 'SOA',
            'content' => $soaContent,
            'ttl' => $options['ttl'] ?? 300,
            'disabled' => false,
        ]);

        // Create NS records
        foreach ($nameservers as $ns) {
            DnsRecord::create([
                'dns_zone_id' => $zone->id,
                'name' => $zone->name,
                'type' => 'NS',
                'content' => $ns,
                'ttl' => $options['ttl'] ?? 300,
                'disabled' => false,
            ]);
        }
    }
}
