<?php

namespace NetServa\Dns\Services;

use Illuminate\Support\Collection;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

class DnsProviderService
{
    /**
     * Initial sync from remote to populate local cache
     */
    public function syncProviderFromRemote(DnsProvider $provider): array
    {
        $results = [
            'zones' => 0,
            'records' => 0,
            'errors' => [],
        ];

        try {
            $client = $provider->getClient();
            $remoteZones = $client->getAllZones();

            foreach ($remoteZones as $remoteZone) {
                try {
                    // Create or update zone in local cache
                    $zone = DnsZone::updateOrCreate(
                        [
                            'dns_provider_id' => $provider->id,
                            'external_id' => $remoteZone['id'] ?? null,
                            'name' => $remoteZone['name'],
                        ],
                        [
                            'kind' => $remoteZone['kind'] ?? 'Master',
                            'serial' => $remoteZone['serial'] ?? null,
                            'last_check' => $remoteZone['last_check'] ? new \DateTime($remoteZone['last_check']) : null,
                            'active' => true,
                            'last_synced' => now(),
                            'provider_data' => $remoteZone,
                        ]
                    );

                    $results['zones']++;

                    // Sync records for this zone
                    $recordCount = $this->syncZoneRecordsFromRemote($zone);
                    $results['records'] += $recordCount;

                } catch (\Exception $e) {
                    $results['errors'][] = "Zone {$remoteZone['name']}: ".$e->getMessage();
                }
            }

            // Update provider sync timestamp
            $provider->update(['last_sync' => now()]);

        } catch (\Exception $e) {
            $results['errors'][] = 'Provider sync failed: '.$e->getMessage();
        }

        return $results;
    }

    /**
     * Sync records for a zone from remote nameserver
     */
    public function syncZoneRecordsFromRemote(DnsZone $zone): int
    {
        $recordCount = 0;

        try {
            $client = $zone->dnsProvider->getClient();
            $remoteRecords = $client->getZoneRecords($zone->external_id);

            // Track existing local records for cleanup
            $existingRecords = $zone->records()->pluck('external_id')->filter();

            foreach ($remoteRecords as $remoteRecord) {
                $record = DnsRecord::updateOrCreate(
                    [
                        'dns_zone_id' => $zone->id,
                        'external_id' => $remoteRecord['id'] ?? null,
                    ],
                    [
                        'name' => $remoteRecord['name'] ?? '',
                        'type' => $remoteRecord['type'],
                        'content' => $remoteRecord['content'],
                        'ttl' => $remoteRecord['ttl'] ?? 3600,
                        'priority' => $remoteRecord['priority'] ?? 0,
                        'disabled' => $remoteRecord['disabled'] ?? false,
                        'auth' => $remoteRecord['auth'] ?? true,
                        'comment' => $remoteRecord['comment'] ?? '',
                        'last_synced' => now(),
                        'provider_data' => $remoteRecord,
                    ]
                );

                $recordCount++;

                // Remove from existing list (cleanup tracker)
                if ($record->external_id) {
                    $existingRecords = $existingRecords->reject($record->external_id);
                }
            }

            // Soft delete records that no longer exist on remote
            if ($existingRecords->isNotEmpty()) {
                DnsRecord::where('dns_zone_id', $zone->id)
                    ->whereIn('external_id', $existingRecords)
                    ->delete();
            }

        } catch (\Exception $e) {
            \Log::error('Failed to sync zone records: '.$e->getMessage());
        }

        return $recordCount;
    }

    /**
     * Create a new zone on remote nameserver and cache locally
     */
    public function createZone(DnsProvider $provider, array $data): ?DnsZone
    {
        try {
            $client = $provider->getClient();

            // 1. Create on remote nameserver FIRST
            $remoteZone = $client->createZone($data);

            // 2. Create in local cache with remote data
            $zone = DnsZone::create([
                'dns_provider_id' => $provider->id,
                'external_id' => $remoteZone['id'] ?? null,
                'name' => $data['name'],
                'kind' => $data['kind'] ?? 'Master',
                'serial' => $remoteZone['serial'] ?? null,
                'active' => true,
                'last_synced' => now(),
                'provider_data' => $remoteZone,
                'description' => $data['description'] ?? null,
            ]);

            return $zone;

        } catch (\Exception $e) {
            \Log::error('Failed to create zone: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a new record on remote nameserver and cache locally
     */
    public function createRecord(DnsZone $zone, array $data): ?DnsRecord
    {
        try {
            $client = $zone->dnsProvider->getClient();

            // 1. Create on remote nameserver FIRST
            $remoteRecord = $client->createRecord($zone->external_id, $data);

            // 2. Create in local cache with remote data
            $record = DnsRecord::create([
                'dns_zone_id' => $zone->id,
                'external_id' => $remoteRecord['id'] ?? null,
                'name' => $data['name'] ?? '',
                'type' => $data['type'],
                'content' => $data['content'],
                'ttl' => $data['ttl'] ?? 3600,
                'priority' => $data['priority'] ?? 0,
                'disabled' => $data['disabled'] ?? false,
                'comment' => $data['comment'] ?? '',
                'last_synced' => now(),
                'provider_data' => $remoteRecord,
            ]);

            // 3. Update zone serial
            $zone->syncFromRemote();

            return $record;

        } catch (\Exception $e) {
            \Log::error('Failed to create record: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Test connection to remote nameserver
     */
    public function testConnection(DnsProvider $provider): bool
    {
        try {
            $client = $provider->getClient();

            return $client->testConnection();
        } catch (\Exception $e) {
            \Log::error('Connection test failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get sync status for all providers
     */
    public function getSyncStatus(): Collection
    {
        return DnsProvider::active()->get()->map(function ($provider) {
            $lastSync = $provider->last_sync;
            $isStale = ! $lastSync || $lastSync->lt(now()->subHours(1));

            return [
                'provider' => $provider,
                'zones' => $provider->zones()->count(),
                'records' => DnsRecord::whereHas('zone', fn ($q) => $q->where('dns_provider_id', $provider->id))->count(),
                'last_sync' => $lastSync,
                'is_stale' => $isStale,
                'is_reachable' => $this->testConnection($provider),
            ];
        });
    }

    /**
     * Force refresh all providers from remote
     */
    public function refreshAllProviders(): array
    {
        $results = [];

        DnsProvider::active()->each(function ($provider) use (&$results) {
            $results[$provider->name] = $this->syncProviderFromRemote($provider);
        });

        return $results;
    }

    /**
     * List zones from a DNS provider
     */
    public function listZones(DnsProvider $provider): array
    {
        try {
            $client = $provider->getClient();

            return $client->getAllZones() ?? [];
        } catch (\Exception $e) {
            \Log::error('Failed to list zones: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Update a DNS record
     */
    public function updateRecord(DnsRecord $record, array $data): bool
    {
        try {
            $client = $record->zone->dnsProvider->getClient();

            // Update on remote first
            $result = $client->updateRecord($record->zone->external_id, $record->external_id, $data);
            $success = ! empty($result);

            if ($success) {
                // Update local cache
                $record->update([
                    'content' => $data['content'] ?? $record->content,
                    'ttl' => $data['ttl'] ?? $record->ttl,
                    'priority' => $data['priority'] ?? $record->priority,
                    'last_synced' => now(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            \Log::error('Failed to update record: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Delete a DNS record
     */
    public function deleteRecord(DnsRecord $record): bool
    {
        try {
            $client = $record->zone->dnsProvider->getClient();

            // Delete from remote first
            $success = $client->deleteRecord($record->zone->external_id, $record->external_id);

            if ($success) {
                // Remove from local cache
                $record->delete();
            }

            return $success;
        } catch (\Exception $e) {
            \Log::error('Failed to delete record: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a record type is valid
     */
    public function isValidRecordType(string $type): bool
    {
        $validTypes = [
            'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'PTR', 'SRV',
            'SOA', 'CAA', 'DNAME', 'DS', 'NAPTR', 'SPF', 'SSHFP',
        ];

        return in_array(strtoupper($type), $validTypes);
    }

    /**
     * Import a zone from DNS provider
     */
    public function importZone(DnsProvider $provider, string $zoneId): ?DnsZone
    {
        try {
            $client = $provider->getClient();
            $remoteZone = $client->getZone($zoneId);

            if (! $remoteZone) {
                return null;
            }

            // Create zone in local cache
            $zone = DnsZone::create([
                'dns_provider_id' => $provider->id,
                'external_id' => $remoteZone['id'],
                'name' => $remoteZone['name'],
                'kind' => $remoteZone['kind'] ?? 'Master',
                'serial' => $remoteZone['serial'] ?? null,
                'active' => true,
                'last_synced' => now(),
                'provider_data' => $remoteZone,
            ]);

            // Import all records for this zone
            $this->syncZoneRecordsFromRemote($zone);

            return $zone;
        } catch (\Exception $e) {
            \Log::error('Failed to import zone: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Export zone to BIND format
     */
    public function exportToBindFormat(DnsZone $zone): string
    {
        $bindFormat = [];

        // Add origin and TTL
        $bindFormat[] = "\$ORIGIN {$zone->name}.";
        $bindFormat[] = '$TTL 3600';
        $bindFormat[] = '';

        // Get all records for this zone
        $records = $zone->records()->orderBy('type')->orderBy('name')->get();

        foreach ($records as $record) {
            $name = $record->name === '@' ? '@' : $record->name;
            $ttl = $record->ttl ?? 3600;
            $type = $record->type;
            $content = $record->content;

            if ($record->type === 'MX' && $record->priority) {
                $content = $record->priority.' '.$content;
            }

            $bindFormat[] = "{$name} {$ttl} IN {$type} {$content}";
        }

        return implode("\n", $bindFormat);
    }

    /**
     * Validate record content based on type
     */
    public function validateRecordContent(string $type, string $content): bool
    {
        switch (strtoupper($type)) {
            case 'A':
                return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            case 'AAAA':
                return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

            case 'CNAME':
            case 'NS':
                return ! empty($content) && strlen($content) > 0;

            case 'MX':
                return ! empty($content) && preg_match('/^(\d+\s+)?[\w\.-]+$/', $content);

            case 'TXT':
                return strlen($content) <= 255;

            case 'SOA':
                $parts = explode(' ', $content);

                return count($parts) >= 7;

            default:
                return ! empty($content);
        }
    }
}
