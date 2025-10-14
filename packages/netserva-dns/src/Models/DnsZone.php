<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DnsZone extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Dns\Database\Factories\DnsZoneFactory::new();
    }

    protected $table = 'dns_zones';

    protected $fillable = [
        'dns_provider_id',
        'external_id',
        'name',
        'kind',
        'masters',
        'serial',
        'last_check',
        'account',
        'active',
        'description',
        'provider_data',
        'last_synced',
        'ttl',
        'auto_dnssec',
        'nameservers',
        'records_count',
        'dnssec_enabled',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_check' => 'datetime',
        'last_synced' => 'datetime',
        'masters' => 'array',
        'nameservers' => 'array',
        'provider_data' => 'array',
        'serial' => 'integer',
        'sort_order' => 'integer',
        'ttl' => 'integer',
        'records_count' => 'integer',
        'auto_dnssec' => 'boolean',
        'dnssec_enabled' => 'boolean',
    ];

    protected $attributes = [
        'kind' => 'Primary',
        'active' => true,
        'sort_order' => 0,
        'ttl' => 3600,
        'auto_dnssec' => false,
        'dnssec_enabled' => false,
        'records_count' => 0,
    ];

    // Computed attributes
    public function getRecordsCountAttribute($value)
    {
        // Return stored value or compute from relationship if not set
        return $value ?? $this->records()->count();
    }

    // Relationships
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByProvider($query, $providerId)
    {
        return $query->where('dns_provider_id', $providerId);
    }

    // Write-Through Cache Operations
    public function createOnRemote(array $data): bool
    {
        $provider = $this->dnsProvider->getClient();

        try {
            // 1. Create on remote nameserver FIRST
            $remoteZone = $provider->createZone($data);

            // 2. Update local cache with remote data
            $this->update([
                'external_id' => $remoteZone['id'] ?? null,
                'serial' => $remoteZone['serial'] ?? $this->getNextSerial(),
                'last_synced' => now(),
                'provider_data' => $remoteZone,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to create zone on remote: '.$e->getMessage());

            return false;
        }
    }

    public function updateOnRemote(array $data): bool
    {
        $provider = $this->dnsProvider->getClient();

        try {
            // 1. Update on remote nameserver FIRST
            $remoteZone = $provider->updateZone($this->external_id, $data);

            // 2. Update local cache with remote response
            $this->update([
                'serial' => $remoteZone['serial'] ?? $this->getNextSerial(),
                'last_synced' => now(),
                'provider_data' => $remoteZone,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to update zone on remote: '.$e->getMessage());

            return false;
        }
    }

    public function deleteOnRemote(): bool
    {
        $provider = $this->dnsProvider->getClient();

        try {
            // 1. Delete from remote nameserver FIRST
            $provider->deleteZone($this->external_id);

            // 2. Soft delete from local cache
            $this->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete zone on remote: '.$e->getMessage());

            return false;
        }
    }

    public function syncFromRemote(): bool
    {
        $provider = $this->dnsProvider->getClient();

        try {
            // Pull latest data from remote nameserver
            $remoteZone = $provider->getZone($this->external_id);

            // Update local cache
            $this->update([
                'serial' => $remoteZone['serial'] ?? $this->serial,
                'last_check' => now(),
                'last_synced' => now(),
                'provider_data' => $remoteZone,
            ]);

            // Sync records too
            $this->syncRecordsFromRemote();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to sync zone from remote: '.$e->getMessage());

            return false;
        }
    }

    public function syncRecordsFromRemote(): void
    {
        $provider = $this->dnsProvider->getClient();

        try {
            $remoteRecords = $provider->getZoneRecords($this->external_id);

            foreach ($remoteRecords as $remoteRecord) {
                DnsRecord::updateOrCreate(
                    [
                        'dns_zone_id' => $this->id,
                        'external_id' => $remoteRecord['id'] ?? null,
                    ],
                    [
                        'name' => $remoteRecord['name'] ?? '',
                        'type' => $remoteRecord['type'],
                        'content' => $remoteRecord['content'],
                        'ttl' => $remoteRecord['ttl'] ?? 300,
                        'priority' => $remoteRecord['priority'] ?? 0,
                        'disabled' => $remoteRecord['disabled'] ?? false,
                        'last_synced' => now(),
                        'provider_data' => $remoteRecord,
                    ]
                );
            }
        } catch (\Exception $e) {
            \Log::error('Failed to sync zone records from remote: '.$e->getMessage());
        }
    }

    // Business Logic
    public function getNextSerial(): int
    {
        $today = now()->format('Ymd');
        $currentSerial = $this->serial ?? 0;

        if (substr($currentSerial, 0, 8) === $today) {
            return $currentSerial + 1;
        }

        return (int) ($today.'01');
    }

    public function isCacheStale(): bool
    {
        if (! $this->last_synced) {
            return true;
        }

        // Consider cache stale after 5 minutes
        return $this->last_synced->lt(now()->subMinutes(5));
    }

    public function getRecordCount(): int
    {
        return $this->records()->count();
    }

    public function getActiveRecordCount(): int
    {
        return $this->records()->where('disabled', false)->count();
    }
}
