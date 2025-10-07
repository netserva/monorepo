<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DnsRecord extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Dns\Database\Factories\DnsRecordFactory::new();
    }

    protected $table = 'dns_records';

    protected $fillable = [
        'dns_zone_id',
        'external_id',
        'name',
        'type',
        'content',
        'ttl',
        'priority',
        'disabled',
        'auth',
        'comment',
        'provider_data',
        'sort_order',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'auth' => 'boolean',
        'ttl' => 'integer',
        'priority' => 'integer',
        'provider_data' => 'array',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'ttl' => 3600,
        'priority' => 0,
        'disabled' => false,
        'auth' => true,
        'sort_order' => 0,
    ];

    // Helper methods
    public function isActive(): bool
    {
        return ! $this->disabled;
    }

    // Relationships
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('disabled', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', strtoupper($type));
    }

    public function scopeByZone($query, $zoneId)
    {
        return $query->where('dns_zone_id', $zoneId);
    }

    // Write-Through Cache Operations
    public function createOnRemote(array $data): bool
    {
        $provider = $this->zone->dnsProvider->getClient();

        try {
            // 1. Create on remote nameserver FIRST
            $remoteRecord = $provider->createRecord($this->zone->external_id, $data);

            // 2. Update local cache with remote data
            $this->update([
                'external_id' => $remoteRecord['id'] ?? null,
                'last_synced' => now(),
                'provider_data' => $remoteRecord,
            ]);

            // 3. Update zone serial if successful
            $this->zone->syncFromRemote();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to create record on remote: '.$e->getMessage());

            return false;
        }
    }

    public function updateOnRemote(array $data): bool
    {
        $provider = $this->zone->dnsProvider->getClient();

        try {
            // 1. Update on remote nameserver FIRST
            $remoteRecord = $provider->updateRecord($this->zone->external_id, $this->external_id, $data);

            // 2. Update local cache with remote response
            $this->update([
                'last_synced' => now(),
                'provider_data' => $remoteRecord,
            ]);

            // 3. Update zone serial if successful
            $this->zone->syncFromRemote();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to update record on remote: '.$e->getMessage());

            return false;
        }
    }

    public function deleteOnRemote(): bool
    {
        $provider = $this->zone->dnsProvider->getClient();

        try {
            // 1. Delete from remote nameserver FIRST
            $provider->deleteRecord($this->zone->external_id, $this->external_id);

            // 2. Soft delete from local cache
            $this->delete();

            // 3. Update zone serial
            $this->zone->syncFromRemote();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete record on remote: '.$e->getMessage());

            return false;
        }
    }

    public function syncFromRemote(): bool
    {
        $provider = $this->zone->dnsProvider->getClient();

        try {
            // Pull latest data from remote nameserver
            $remoteRecord = $provider->getRecord($this->zone->external_id, $this->external_id);

            // Update local cache
            $this->update([
                'name' => $remoteRecord['name'] ?? $this->name,
                'type' => $remoteRecord['type'] ?? $this->type,
                'content' => $remoteRecord['content'] ?? $this->content,
                'ttl' => $remoteRecord['ttl'] ?? $this->ttl,
                'priority' => $remoteRecord['priority'] ?? $this->priority,
                'disabled' => $remoteRecord['disabled'] ?? $this->disabled,
                'last_synced' => now(),
                'provider_data' => $remoteRecord,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to sync record from remote: '.$e->getMessage());

            return false;
        }
    }

    public function isCacheStale(): bool
    {
        if (! $this->last_synced) {
            return true;
        }

        // Consider cache stale after 5 minutes
        return $this->last_synced->lt(now()->subMinutes(5));
    }

    // Business Logic
    public function getFormattedName(): string
    {
        if (empty($this->name) || $this->name === '@') {
            return $this->zone->name;
        }

        if (str_ends_with($this->name, '.')) {
            return rtrim($this->name, '.');
        }

        return $this->name.'.'.rtrim($this->zone->name, '.');
    }

    public function isApexRecord(): bool
    {
        return empty($this->name) || $this->name === '@';
    }

    public function requiresPriority(): bool
    {
        return in_array($this->type, ['MX', 'SRV']);
    }

    public function getDisplayPriority(): ?int
    {
        return $this->requiresPriority() ? $this->priority : null;
    }

    public function validateContent(): bool
    {
        return match ($this->type) {
            'A' => filter_var($this->content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
            'AAAA' => filter_var($this->content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
            'MX' => ! empty($this->content) && ! empty($this->priority),
            'CNAME', 'NS', 'PTR' => ! empty($this->content),
            'TXT' => true, // TXT records can contain any content
            default => ! empty($this->content)
        };
    }
}
