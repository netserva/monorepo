<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Dns\Services\DnsProviderFactory;

class DnsProvider extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Dns\Database\Factories\DnsProviderFactory::new();
    }

    protected $table = 'dns_providers';

    protected $fillable = [
        'type',
        'name',
        'description',
        'active',
        'version',
        'connection_config',
        'sync_config',
        'last_sync',
        'rate_limit',
        'timeout',
        'sort_order',
    ];

    protected $casts = [
        'connection_config' => 'array',
        'sync_config' => 'array',
        'active' => 'boolean',
        'last_sync' => 'datetime',
        'sort_order' => 'integer',
        'rate_limit' => 'integer',
        'timeout' => 'integer',
    ];

    protected $attributes = [
        'active' => true,
        'type' => 'powerdns',
        'sort_order' => 0,
        'connection_config' => '{}',
        'rate_limit' => 100,
        'timeout' => 30,
    ];

    // Convenience accessors for connection config
    public function getApiKey(): ?string
    {
        return $this->connection_config['api_key'] ?? null;
    }

    public function setApiKey(string $apiKey): void
    {
        $config = $this->connection_config ?? [];
        $config['api_key'] = $apiKey;
        $this->connection_config = $config;
    }

    public function getApiSecret(): ?string
    {
        return $this->connection_config['api_secret'] ?? null;
    }

    public function setApiSecret(string $apiSecret): void
    {
        $config = $this->connection_config ?? [];
        $config['api_secret'] = $apiSecret;
        $this->connection_config = $config;
    }

    public function getApiEndpoint(): ?string
    {
        return $this->connection_config['api_endpoint'] ?? null;
    }

    public function setApiEndpoint(string $endpoint): void
    {
        $config = $this->connection_config ?? [];
        $config['api_endpoint'] = $endpoint;
        $this->connection_config = $config;
    }

    public function getCredentials(): array
    {
        return $this->connection_config['credentials'] ?? [];
    }

    public function setCredentials(array $credentials): void
    {
        $config = $this->connection_config ?? [];
        $config['credentials'] = $credentials;
        $this->connection_config = $config;
    }

    // Relationships
    public function zones(): HasMany
    {
        return $this->hasMany(DnsZone::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(DnsServer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Business Logic
    public function getClient()
    {
        return DnsProviderFactory::make($this);
    }

    public function isReachable(): bool
    {
        try {
            $client = $this->getClient();

            return $client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getZoneCount(): int
    {
        return $this->zones()->count();
    }

    public function getActiveZoneCount(): int
    {
        return $this->zones()->where('active', true)->count();
    }

    public function getTotalRecordCount(): int
    {
        return DnsRecord::whereHas('zone', function ($query) {
            $query->where('dns_provider_id', $this->id);
        })->count();
    }

    public function syncZones(): array
    {
        $client = $this->getClient();

        return $client->syncZones();
    }

    public function getConnectionSummary(): string
    {
        $config = $this->connection_config;

        return match ($this->type) {
            'powerdns' => sprintf(
                '%s:%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? '8081'
            ),
            'cloudflare' => 'CloudFlare API',
            'route53' => 'AWS Route53',
            default => $this->name
        };
    }
}
