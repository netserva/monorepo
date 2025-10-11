<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

/**
 * DNS Record Management Service
 *
 * Provides complete CRUD operations for DNS records
 * Shared service layer between CLI commands and Filament UI
 *
 * Features:
 * - All record types (A, AAAA, CNAME, MX, TXT, PTR, NS, SRV, etc.)
 * - Automatic PTR record creation for A/AAAA records (FCrDNS)
 * - Content validation per record type
 * - PowerDNS RRset management
 *
 * Tier 3 in DNS Hierarchy:
 * - Provider → Zone → Record
 * - Each record belongs to one zone
 *
 * @package NetServa\Dns\Services
 */
class DnsRecordManagementService
{
    public function __construct(
        protected PowerDnsTunnelService $tunnelService,
        protected PowerDnsService $powerDnsService
    ) {}

    /**
     * Create a new DNS record
     *
     * @param  string  $type  Record type (A, AAAA, CNAME, MX, TXT, PTR, NS, SRV, etc.)
     * @param  string  $name  Record name (e.g., "www", "@", "mail.example.com.")
     * @param  int|string  $zoneId  Zone ID or name
     * @param  string  $content  Record content (IP, hostname, text, etc.)
     * @param  array  $options  Additional options
     * @return array Result with success status and record data
     */
    public function createRecord(
        string $type,
        string $name,
        int|string $zoneId,
        string $content,
        array $options = []
    ): array {
        try {
            // Find zone
            $zoneResult = $this->findZone($zoneId);
            if (! $zoneResult['success']) {
                return $zoneResult;
            }

            $zone = $zoneResult['zone'];
            $provider = $zone->dnsProvider;

            // Normalize record type
            $type = strtoupper($type);

            // Normalize record name
            $name = $this->normalizeRecordName($name, $zone->name);

            // Validate content for record type
            $validation = $this->validateRecordContent($type, $content, $options);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            // Check for duplicate record
            if (! ($options['allow_duplicate'] ?? false)) {
                $existing = DnsRecord::where('dns_zone_id', $zone->id)
                    ->where('name', $name)
                    ->where('type', $type)
                    ->where('content', $content)
                    ->first();

                if ($existing) {
                    return [
                        'success' => false,
                        'message' => "Record already exists: {$type} {$name} → {$content}",
                        'record' => $existing,
                    ];
                }
            }

            DB::beginTransaction();

            try {
                // Prepare record data for remote creation
                $recordData = [
                    'name' => $name,
                    'type' => $type,
                    'content' => $content,
                    'ttl' => $options['ttl'] ?? $zone->ttl ?? 3600,
                    'priority' => $options['priority'] ?? 0,
                    'disabled' => $options['disabled'] ?? false,
                ];

                // Create record on remote provider FIRST
                $remoteResult = $this->createRecordOnRemote($provider, $zone, $recordData);

                if (! $remoteResult['success']) {
                    throw new Exception($remoteResult['message']);
                }

                // Create record in local database
                $record = DnsRecord::create([
                    'dns_zone_id' => $zone->id,
                    'external_id' => $remoteResult['data']['id'] ?? null,
                    'name' => $name,
                    'type' => $type,
                    'content' => $content,
                    'ttl' => $recordData['ttl'],
                    'priority' => $recordData['priority'],
                    'disabled' => $recordData['disabled'],
                    'comment' => $options['comment'] ?? null,
                    'provider_data' => $remoteResult['data'] ?? [],
                    'sort_order' => $options['sort_order'] ?? 0,
                ]);

                DB::commit();

                $result = [
                    'success' => true,
                    'message' => "Record created: {$type} {$name} → {$content}",
                    'record' => $record->fresh(),
                    'zone' => $zone,
                ];

                // Auto-create PTR record for A/AAAA records
                if (in_array($type, ['A', 'AAAA']) && ($options['auto_ptr'] ?? false)) {
                    $ptrResult = $this->createAutoPtrRecord($record, $options);
                    $result['ptr_record'] = $ptrResult;
                }

                return $result;

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Record creation failed', [
                    'zone' => $zone->id,
                    'type' => $type,
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create record: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Record creation error: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List DNS records with optional filtering
     *
     * @param  array  $filters  Filter criteria
     * @return Collection
     */
    public function listRecords(array $filters = []): Collection
    {
        $query = DnsRecord::query()
            ->with(['zone.dnsProvider'])
            ->orderBy('sort_order')
            ->orderBy('type')
            ->orderBy('name');

        // Filter by zone
        if (isset($filters['zone'])) {
            $zoneResult = $this->findZone($filters['zone']);
            if ($zoneResult['success']) {
                $query->where('dns_zone_id', $zoneResult['zone']->id);
            }
        }

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', strtoupper($filters['type']));
        }

        // Filter by active status
        if (isset($filters['active'])) {
            $query->where('disabled', ! $filters['active']);
        }

        // Search by name
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('content', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by content (for finding specific IPs, hostnames, etc.)
        if (isset($filters['content'])) {
            $query->where('content', 'like', '%' . $filters['content'] . '%');
        }

        return $query->get();
    }

    /**
     * Show detailed information about a DNS record
     *
     * @param  int|string  $identifier  Record ID
     * @param  array  $options  Display options
     * @return array Result with record data
     */
    public function showRecord(int|string $identifier, array $options = []): array
    {
        try {
            $recordResult = $this->findRecord($identifier);
            if (! $recordResult['success']) {
                return $recordResult;
            }

            $record = $recordResult['record'];
            $zone = $record->zone;
            $provider = $zone->dnsProvider;

            $result = [
                'success' => true,
                'record' => $record->load(['zone.dnsProvider']),
                'zone' => $zone,
                'provider' => $provider,
            ];

            // Find related PTR record if this is an A/AAAA record
            if (in_array($record->type, ['A', 'AAAA']) && ($options['with_ptr'] ?? false)) {
                $ptrRecord = $this->findPtrRecord($record->content);
                $result['ptr_record'] = $ptrRecord;
            }

            // Find related A/AAAA record if this is a PTR record
            if ($record->type === 'PTR' && ($options['with_forward'] ?? false)) {
                $forwardRecord = $this->findForwardRecord($record);
                $result['forward_record'] = $forwardRecord;
            }

            // Sync from remote if requested
            if ($options['sync'] ?? false) {
                $syncResult = $this->syncRecordFromRemote($record);
                $result['sync_result'] = $syncResult;
                $result['record'] = $record->fresh();
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to show record: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update DNS record
     *
     * @param  int|string  $identifier  Record ID
     * @param  array  $updates  Fields to update
     * @param  array  $options  Update options
     * @return array Result with updated record
     */
    public function updateRecord(int|string $identifier, array $updates, array $options = []): array
    {
        try {
            $recordResult = $this->findRecord($identifier);
            if (! $recordResult['success']) {
                return $recordResult;
            }

            $record = $recordResult['record'];
            $zone = $record->zone;
            $provider = $zone->dnsProvider;
            $changes = [];

            // Validate content if being updated
            if (isset($updates['content'])) {
                $validation = $this->validateRecordContent(
                    $updates['type'] ?? $record->type,
                    $updates['content'],
                    $updates
                );

                if (! $validation['valid']) {
                    return [
                        'success' => false,
                        'message' => $validation['message'],
                    ];
                }
            }

            DB::beginTransaction();

            try {
                // Track changes
                foreach ($updates as $field => $value) {
                    if (isset($record->$field) && $record->$field !== $value) {
                        $changes[$field] = [
                            'old' => $record->$field,
                            'new' => $value,
                        ];
                    }
                }

                // Update on remote first if critical fields changed
                $remoteFields = ['name', 'type', 'content', 'ttl', 'priority', 'disabled'];
                $needsRemoteUpdate = ! empty(array_intersect(array_keys($updates), $remoteFields));

                if ($needsRemoteUpdate) {
                    $remoteData = array_intersect_key($updates, array_flip($remoteFields));

                    $remoteResult = $this->updateRecordOnRemote($provider, $zone, $record, $remoteData);
                    if (! $remoteResult['success']) {
                        throw new Exception($remoteResult['message']);
                    }
                }

                // Update local database
                $record->update($updates);

                // Update related PTR record if content changed
                if (isset($updates['content']) && in_array($record->type, ['A', 'AAAA'])) {
                    if ($options['update_ptr'] ?? false) {
                        $ptrResult = $this->updateAutoPtrRecord($record, $changes['content']['old'], $options);
                        $changes['ptr_updated'] = $ptrResult;
                    }
                }

                DB::commit();

                return [
                    'success' => true,
                    'message' => "Record updated: {$record->type} {$record->name}",
                    'record' => $record->fresh(),
                    'changes' => $changes,
                ];

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Record update failed', [
                    'record' => $record->id,
                    'updates' => $updates,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to update record: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Record update error: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete DNS record
     *
     * @param  int|string  $identifier  Record ID
     * @param  array  $options  Deletion options
     * @return array Result with deletion status
     */
    public function deleteRecord(int|string $identifier, array $options = []): array
    {
        try {
            $recordResult = $this->findRecord($identifier);
            if (! $recordResult['success']) {
                return $recordResult;
            }

            $record = $recordResult['record'];
            $zone = $record->zone;
            $provider = $zone->dnsProvider;

            DB::beginTransaction();

            try {
                // Delete from remote first (unless skip-remote)
                if (! ($options['skip_remote'] ?? false)) {
                    $remoteResult = $this->deleteRecordOnRemote($provider, $zone, $record);
                    if (! $remoteResult['success'] && ! ($options['force'] ?? false)) {
                        throw new Exception($remoteResult['message']);
                    }
                }

                // Delete related PTR record if this is an A/AAAA record
                $ptrDeleted = null;
                if (in_array($record->type, ['A', 'AAAA']) && ($options['delete_ptr'] ?? false)) {
                    $ptrResult = $this->deleteAutoPtrRecord($record, $options);
                    $ptrDeleted = $ptrResult;
                }

                // Soft delete the record
                $recordInfo = "{$record->type} {$record->name} → {$record->content}";
                $record->delete();

                DB::commit();

                return [
                    'success' => true,
                    'message' => "Record deleted: {$recordInfo}",
                    'record' => $record,
                    'ptr_deleted' => $ptrDeleted,
                ];

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Record deletion failed', [
                    'record' => $record->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to delete record: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Record deletion error: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create automatic PTR record for A/AAAA record (FCrDNS)
     *
     * @param  DnsRecord  $record  A or AAAA record
     * @param  array  $options  PTR creation options
     * @return array|null PTR record result or null if not created
     */
    protected function createAutoPtrRecord(DnsRecord $record, array $options = []): ?array
    {
        try {
            // Generate PTR zone name from IP
            $ptrZoneName = $this->generatePtrZoneName($record->content, $record->type);

            if (! $ptrZoneName) {
                return [
                    'success' => false,
                    'message' => 'Could not generate PTR zone name from IP: ' . $record->content,
                ];
            }

            // Find or create PTR zone
            $ptrZone = DnsZone::where('name', $ptrZoneName)->first();

            if (! $ptrZone) {
                // Optionally create PTR zone automatically
                if ($options['auto_create_ptr_zone'] ?? false) {
                    $zoneResult = app(DnsZoneManagementService::class)->createZone(
                        zoneName: $ptrZoneName,
                        providerId: $record->zone->dns_provider_id,
                        options: [
                            'kind' => 'Master',
                            'auto_dnssec' => false,
                        ]
                    );

                    if (! $zoneResult['success']) {
                        return [
                            'success' => false,
                            'message' => 'Failed to create PTR zone: ' . $zoneResult['message'],
                        ];
                    }

                    $ptrZone = $zoneResult['zone'];
                } else {
                    return [
                        'success' => false,
                        'message' => "PTR zone '{$ptrZoneName}' does not exist. Use --auto-create-ptr-zone to create it.",
                    ];
                }
            }

            // Generate PTR record name
            $ptrRecordName = $this->generatePtrRecordName($record->content, $record->type);

            // PTR content is the FQDN of the A/AAAA record
            $ptrContent = $record->getFormattedName();
            if (! str_ends_with($ptrContent, '.')) {
                $ptrContent .= '.';
            }

            // Create PTR record
            return $this->createRecord(
                type: 'PTR',
                name: $ptrRecordName,
                zoneId: $ptrZone->id,
                content: $ptrContent,
                options: [
                    'ttl' => $options['ttl'] ?? $record->ttl,
                    'comment' => "Auto-created for {$record->type} {$record->name}",
                ]
            );

        } catch (Exception $e) {
            Log::error('Auto-PTR creation failed', [
                'record' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Auto-PTR failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update automatic PTR record when A/AAAA record changes
     */
    protected function updateAutoPtrRecord(DnsRecord $record, string $oldIp, array $options = []): ?array
    {
        // Delete old PTR, create new one
        $deleteResult = $this->deleteAutoPtrRecord($record, $options, $oldIp);
        $createResult = $this->createAutoPtrRecord($record, $options);

        return [
            'old_ptr_deleted' => $deleteResult,
            'new_ptr_created' => $createResult,
        ];
    }

    /**
     * Delete automatic PTR record for A/AAAA record
     */
    protected function deleteAutoPtrRecord(DnsRecord $record, array $options = [], ?string $ip = null): ?array
    {
        $ip = $ip ?? $record->content;
        $ptrRecord = $this->findPtrRecord($ip);

        if (! $ptrRecord) {
            return null;
        }

        return $this->deleteRecord($ptrRecord->id, $options);
    }

    /**
     * Find PTR record for given IP
     */
    protected function findPtrRecord(string $ip): ?DnsRecord
    {
        $ptrZoneName = $this->generatePtrZoneName($ip, filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'A' : 'AAAA');
        $ptrRecordName = $this->generatePtrRecordName($ip, filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'A' : 'AAAA');

        $ptrZone = DnsZone::where('name', $ptrZoneName)->first();
        if (! $ptrZone) {
            return null;
        }

        return DnsRecord::where('dns_zone_id', $ptrZone->id)
            ->where('type', 'PTR')
            ->where('name', $ptrRecordName)
            ->first();
    }

    /**
     * Find forward (A/AAAA) record from PTR record
     */
    protected function findForwardRecord(DnsRecord $ptrRecord): ?DnsRecord
    {
        $fqdn = rtrim($ptrRecord->content, '.');

        return DnsRecord::whereIn('type', ['A', 'AAAA'])
            ->where(function ($q) use ($fqdn) {
                $q->where('name', $fqdn)
                    ->orWhere(DB::raw("CONCAT(name, '.', (SELECT name FROM dns_zones WHERE id = dns_records.dns_zone_id))"), $fqdn);
            })
            ->first();
    }

    /**
     * Generate PTR zone name from IP address
     *
     * Examples:
     * - 192.168.1.100 → 1.168.192.in-addr.arpa.
     * - 2001:db8::1 → 0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa.
     */
    protected function generatePtrZoneName(string $ip, string $type): ?string
    {
        if ($type === 'A') {
            // IPv4: 192.168.1.100 → 1.168.192.in-addr.arpa.
            $octets = explode('.', $ip);
            if (count($octets) !== 4) {
                return null;
            }

            // Use /24 for PTR zone (first 3 octets)
            return $octets[2] . '.' . $octets[1] . '.' . $octets[0] . '.in-addr.arpa.';
        }

        if ($type === 'AAAA') {
            // IPv6: Expand to full notation, reverse nibbles
            // This is complex, simplified version for /64
            $expanded = inet_ntop(inet_pton($ip));
            $hex = str_replace(':', '', $expanded);
            $nibbles = str_split(strrev($hex));

            // Use /64 for PTR zone (first 16 nibbles)
            $ptrNibbles = array_slice($nibbles, 0, 16);

            return implode('.', $ptrNibbles) . '.ip6.arpa.';
        }

        return null;
    }

    /**
     * Generate PTR record name from IP address
     *
     * Examples:
     * - 192.168.1.100 (in zone 1.168.192.in-addr.arpa.) → 100
     * - 2001:db8::1 → (last nibble)
     */
    protected function generatePtrRecordName(string $ip, string $type): string
    {
        if ($type === 'A') {
            $octets = explode('.', $ip);

            return $octets[3]; // Last octet
        }

        if ($type === 'AAAA') {
            // IPv6: Last nibble
            $expanded = inet_ntop(inet_pton($ip));
            $hex = str_replace(':', '', $expanded);

            return substr($hex, -1);
        }

        return '';
    }

    /**
     * Normalize record name
     */
    protected function normalizeRecordName(string $name, string $zoneName): string
    {
        // @ means apex/root
        if ($name === '@' || empty($name)) {
            return rtrim($zoneName, '.') . '.';
        }

        // Already FQDN
        if (str_ends_with($name, '.')) {
            return $name;
        }

        // Relative name - append zone
        return $name . '.' . rtrim($zoneName, '.') . '.';
    }

    /**
     * Validate record content for record type
     */
    protected function validateRecordContent(string $type, string $content, array $options = []): array
    {
        $valid = match ($type) {
            'A' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'AAAA' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'MX' => ! empty($content) && isset($options['priority']),
            'CNAME', 'NS', 'PTR' => ! empty($content),
            'TXT' => true, // TXT can contain any content
            'SRV' => ! empty($content) && isset($options['priority']),
            default => ! empty($content)
        };

        $message = $valid ? 'Valid' : match ($type) {
            'A' => 'Invalid IPv4 address',
            'AAAA' => 'Invalid IPv6 address',
            'MX' => 'MX record requires content and --priority',
            'SRV' => 'SRV record requires content and --priority',
            'CNAME', 'NS', 'PTR' => 'Content cannot be empty',
            default => 'Invalid content for ' . $type . ' record'
        };

        return [
            'valid' => $valid,
            'message' => $message,
        ];
    }

    /**
     * Sync record from remote provider
     */
    protected function syncRecordFromRemote(DnsRecord $record): array
    {
        try {
            // Implementation depends on provider API
            // For now, return success
            return [
                'success' => true,
                'message' => 'Sync not yet implemented',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ];
        }
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
            $normalizedName = rtrim($identifier, '.') . '.';
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
     * Find record by ID
     */
    protected function findRecord(int $identifier): array
    {
        $record = DnsRecord::find($identifier);

        if (! $record) {
            return [
                'success' => false,
                'message' => "Record ID {$identifier} not found",
            ];
        }

        return [
            'success' => true,
            'record' => $record,
        ];
    }

    /**
     * Create record on remote provider
     */
    protected function createRecordOnRemote($provider, DnsZone $zone, array $recordData): array
    {
        try {
            return $this->powerDnsService->createRecord($provider, $zone->name, $recordData);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote record creation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update record on remote provider
     */
    protected function updateRecordOnRemote($provider, DnsZone $zone, DnsRecord $record, array $updateData): array
    {
        try {
            // Delete old record and create new one (PowerDNS pattern)
            $deleteResult = $this->powerDnsService->deleteRecord($provider, $zone->name, $record->name, $record->type);

            if (! $deleteResult['success']) {
                throw new Exception($deleteResult['message']);
            }

            return $this->powerDnsService->createRecord($provider, $zone->name, array_merge([
                'name' => $record->name,
                'type' => $record->type,
                'content' => $record->content,
                'ttl' => $record->ttl,
                'priority' => $record->priority,
            ], $updateData));
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote record update failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete record on remote provider
     */
    protected function deleteRecordOnRemote($provider, DnsZone $zone, DnsRecord $record): array
    {
        try {
            return $this->powerDnsService->deleteRecord($provider, $zone->name, $record->name, $record->type);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Remote record deletion failed: ' . $e->getMessage(),
            ];
        }
    }
}
