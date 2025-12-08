<?php

declare(strict_types=1);

namespace NetServa\Crm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Database\Factories\CrmClientFactory;

/**
 * CRM Client Model
 *
 * Unified client model - no separate company/individual distinction.
 * Business clients have optional company_name, ABN, ACN fields.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $company_name
 * @property string|null $abn
 * @property string|null $acn
 * @property string $email
 * @property string|null $home_phone
 * @property string|null $work_phone
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string $country
 * @property string|null $notes
 * @property array|null $metadata
 * @property string|null $external_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class CrmClient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_clients';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'first_name',
        'last_name',
        'company_name',
        'abn',
        'acn',
        'email',
        'home_phone',
        'work_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country',
        'notes',
        'metadata',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'active',
        'country' => 'AU',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $client) {
            // Auto-generate name if not provided
            if (empty($client->name)) {
                $client->name = $client->generateDisplayName();
            }

            // Auto-generate slug if not provided
            if (empty($client->slug)) {
                $client->slug = static::generateUniqueSlug($client->name);
            }
        });

        static::updating(function (self $client) {
            // Regenerate slug if name changed and slug wasn't explicitly set
            if ($client->isDirty('name') && ! $client->isDirty('slug')) {
                $client->slug = static::generateUniqueSlug($client->name, $client->id);
            }
        });
    }

    /**
     * Generate display name from available fields
     * Priority: first_name + last_name, never company_name
     */
    public function generateDisplayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name ?: ($this->first_name ?? $this->last_name ?? 'Unnamed Client');
    }

    /**
     * Generate a unique slug for the client
     */
    protected static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $maxLength = config('netserva-crm.slug.max_length', 50);
        $baseSlug = Str::limit($baseSlug, $maxLength, '');

        $slug = $baseSlug;
        $counter = 1;

        $query = static::withTrashed()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
            $query = static::withTrashed()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    protected static function newFactory(): CrmClientFactory
    {
        return CrmClientFactory::new();
    }

    // ========================
    // Conditional Relationships (Fleet Integration)
    // ========================

    /**
     * Get VSites owned by this client (if Fleet package is installed)
     */
    public function vsites(): HasMany
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            return $this->hasMany(self::class, 'id')->whereRaw('1=0');
        }

        return $this->hasMany(\NetServa\Fleet\Models\FleetVsite::class, 'customer_id');
    }

    /**
     * Get VNodes owned by this client (through VSites)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVnodesAttribute()
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            return collect();
        }

        return \NetServa\Fleet\Models\FleetVnode::query()
            ->whereHas('vsite', fn ($q) => $q->where('customer_id', $this->id))
            ->get();
    }

    /**
     * Get VHosts owned by this client (through VNodes -> VSites)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVhostsAttribute()
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            return collect();
        }

        return \NetServa\Fleet\Models\FleetVhost::query()
            ->whereHas('vnode.vsite', fn ($q) => $q->where('customer_id', $this->id))
            ->get();
    }

    // ========================
    // Conditional Relationships (Domain Integration)
    // ========================

    /**
     * Get domains owned by this client (if SwDomain model exists)
     */
    public function domains(): HasMany
    {
        if (! CrmServiceProvider::hasDomainIntegration()) {
            return $this->hasMany(self::class, 'id')->whereRaw('1=0');
        }

        return $this->hasMany(\App\Models\SwDomain::class, 'customer_id');
    }

    // ========================
    // Integration Checks
    // ========================

    public function hasFleetIntegration(): bool
    {
        return CrmServiceProvider::hasFleetIntegration();
    }

    public function hasDomainIntegration(): bool
    {
        return CrmServiceProvider::hasDomainIntegration();
    }

    // ========================
    // Scopes
    // ========================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeProspect($query)
    {
        return $query->where('status', 'prospect');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeBusiness($query)
    {
        return $query->whereNotNull('company_name');
    }

    public function scopePersonal($query)
    {
        return $query->whereNull('company_name');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('abn', 'like', "%{$term}%");
        });
    }

    // ========================
    // Accessors
    // ========================

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getFullNameAttribute(): ?string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name ?: null;
    }

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return empty($parts) ? null : implode(', ', $parts);
    }

    public function getFormattedAbnAttribute(): ?string
    {
        if (! $this->abn) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->abn);

        return substr($digits, 0, 2).' '.
            substr($digits, 2, 3).' '.
            substr($digits, 5, 3).' '.
            substr($digits, 8, 3);
    }

    public function getIsBusinessAttribute(): bool
    {
        return ! empty($this->company_name);
    }

    public function getVsiteCountAttribute(): int
    {
        if (! $this->hasFleetIntegration()) {
            return 0;
        }

        return $this->vsites()->count();
    }

    public function getVnodeCountAttribute(): int
    {
        if (! $this->hasFleetIntegration()) {
            return 0;
        }

        return $this->vnodes->count();
    }

    public function getVhostCountAttribute(): int
    {
        if (! $this->hasFleetIntegration()) {
            return 0;
        }

        return $this->vhosts->count();
    }

    public function getDomainCountAttribute(): int
    {
        if (! $this->hasDomainIntegration()) {
            return 0;
        }

        return $this->domains()->count();
    }

    // ========================
    // Status Helpers
    // ========================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProspect(): bool
    {
        return $this->status === 'prospect';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // ========================
    // Metadata Helpers
    // ========================

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function setMeta(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }

    public function hasMeta(string $key): bool
    {
        return data_get($this->metadata, $key) !== null;
    }
}
