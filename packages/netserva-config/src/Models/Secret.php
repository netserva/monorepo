<?php

namespace NetServa\Config\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Secret extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Secrets\Database\Factories\SecretFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'encrypted_value',
        'encryption_method',
        'metadata',
        'secret_category_id',
        'tags',
        'is_active',
        'is_encrypted',
        'expires_at',
        'ssh_host_reference',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_encrypted' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_encrypted' => false,
    ];

    protected $hidden = [
        'encrypted_value', // Never include encrypted value in serialization
    ];

    // Types available for secrets
    public const TYPES = [
        'password' => 'Password',
        'api_key' => 'API Key',
        'ssh_private_key' => 'SSH Private Key',
        'certificate' => 'Certificate',
        'token' => 'Token',
        'connection_string' => 'Connection String',
        'environment_variable' => 'Environment Variable',
        'other' => 'Other',
    ];

    // Environments available
    public const ENVIRONMENTS = [
        'production' => 'Production',
        'staging' => 'Staging',
        'development' => 'Development',
        'testing' => 'Testing',
    ];

    // Access types for audit logging
    public const ACCESS_TYPES = [
        'view' => 'Viewed',
        'copy' => 'Copied',
        'download' => 'Downloaded',
        'api' => 'API Access',
        'migration' => 'Migration Access',
    ];

    /**
     * Get the decrypted value (accessor with audit logging)
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (empty($this->encrypted_value)) {
                    return null;
                }

                try {
                    $decrypted = decrypt($this->encrypted_value);

                    // Log access
                    $this->logAccess('view');

                    return $decrypted;
                } catch (\Exception $e) {
                    \Log::error('Failed to decrypt secret', [
                        'secret_id' => $this->id,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            },
            set: function (string $value): void {
                $this->attributes['encrypted_value'] = encrypt($value);
                $this->attributes['encryption_method'] = 'laravel-crypt';
            }
        );
    }

    /**
     * Check if secret is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if secret is accessible (active and not expired)
     */
    public function getIsAccessibleAttribute(): bool
    {
        return $this->is_active && ! $this->is_expired;
    }

    /**
     * Log access to this secret
     */
    public function logAccess(string $accessType, ?User $user = null, array $context = []): void
    {
        $userId = $user?->id ?? auth()->id();

        $this->secretAccesses()->create([
            'user_id' => $userId,
            'access_type' => $accessType,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'source' => $context['source'] ?? 'web',
            'additional_context' => $context,
            'accessed_at' => now(),
        ]);

        // Create audit log if audit is available
        if (class_exists(\NetServa\Ops\Models\AuditLog::class)) {
            $auditUser = $user ?? auth()->user();

            \NetServa\Ops\Models\AuditLog::create([
                'user_id' => $userId,
                'username' => $auditUser?->name ?? 'System',
                'event_type' => $accessType,
                'event_category' => 'security',
                'resource_type' => 'secret',
                'resource_id' => $this->id,
                'resource_name' => $this->name,
                'description' => "Accessed secret '{$this->name}'",
                'severity_level' => 'medium',
                'status' => 'success',
                'additional_context' => array_merge($context, [
                    'secret_slug' => $this->slug,
                    'secret_type' => $this->type,
                ]),
            ]);
        }
    }

    /**
     * Get a safe version for API/display (without the actual secret)
     */
    public function toSafeArray(): array
    {
        return $this->only([
            'id', 'name', 'slug', 'type', 'description',
            'tags', 'is_active', 'expires_at', 'created_at', 'updated_at',
        ]);
    }

    // Relationships

    public function secretCategory(): BelongsTo
    {
        return $this->belongsTo(SecretCategory::class);
    }

    public function secretAccesses(): HasMany
    {
        return $this->hasMany(SecretAccess::class);
    }

    /**
     * Get SSH hosts that use this secret for authentication
     */
    public function sshHosts(): HasMany
    {
        return $this->hasMany(\NetServa\Core\Models\SshHost::class, 'ssh_key_secret_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeAccessible($query)
    {
        return $query->active()->notExpired();
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('secret_category_id', $categoryId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByEnvironment($query, string $environment)
    {
        // Environment column was removed in simplification migration
        // Return query unchanged for backward compatibility
        return $query;
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
