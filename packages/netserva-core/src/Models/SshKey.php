<?php

declare(strict_types=1);

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NetServa\Core\Services\SshKeySyncService;

/**
 * NetServa Core SSH Key Model
 *
 * Represents an SSH key pair used for authentication.
 * Keys are stored in database and synced to ~/.ssh/keys/
 */
class SshKey extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\SshKeyFactory::new();
    }

    protected $fillable = [
        'name',
        'type',
        'key_size',
        'public_key',
        'private_key',
        'fingerprint',
        'comment',
        'has_passphrase',
        'is_active',
        'last_used_at',
        'description',
    ];

    protected $casts = [
        'has_passphrase' => 'boolean',
        'is_active' => 'boolean',
        'key_size' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'private_key',
    ];

    protected $attributes = [
        'type' => 'ed25519',
        'is_active' => true,
        'has_passphrase' => false,
    ];

    /**
     * Get SSH hosts using this key
     */
    public function sshHosts(): HasMany
    {
        return $this->hasMany(SshHost::class, 'identity_file', 'name');
    }

    /**
     * Scope to get only active keys
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the key type with size for display
     */
    public function getDisplayTypeAttribute(): string
    {
        $type = strtoupper($this->type);

        if ($this->key_size && $this->type === 'rsa') {
            return "{$type} {$this->key_size}";
        }

        return $type;
    }

    /**
     * Get short fingerprint for display
     */
    public function getShortFingerprintAttribute(): string
    {
        if (! $this->fingerprint) {
            return 'Unknown';
        }

        // Show last 16 characters
        return '...'.substr($this->fingerprint, -16);
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "Key {$this->id}";
    }

    /**
     * Check if this key has been used recently
     */
    public function isRecentlyUsed(int $days = 7): bool
    {
        if (! $this->last_used_at) {
            return false;
        }

        return $this->last_used_at->isAfter(now()->subDays($days));
    }

    /**
     * Get the comment or extract from public key
     */
    public function getEffectiveCommentAttribute(): string
    {
        if ($this->comment) {
            return $this->comment;
        }

        // Try to extract from public key
        if ($this->public_key) {
            $parts = explode(' ', trim($this->public_key));
            if (count($parts) >= 3) {
                return $parts[2];
            }
        }

        return "{$this->name}@netserva";
    }

    /**
     * Get full path to private key file
     */
    public function getPrivateKeyPathAttribute(): string
    {
        $home = env('HOME', '/home/'.get_current_user());

        return "{$home}/.ssh/keys/{$this->name}";
    }

    /**
     * Get full path to public key file
     */
    public function getPublicKeyPathAttribute(): string
    {
        return $this->private_key_path.'.pub';
    }

    /**
     * Check if key files exist on filesystem
     */
    public function filesExist(): bool
    {
        return file_exists($this->private_key_path) &&
               file_exists($this->public_key_path);
    }

    /**
     * Get hosts count using this key
     */
    public function getHostsCountAttribute(): int
    {
        return $this->sshHosts()->count();
    }

    /**
     * Sync to filesystem and update observers
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            // Sync key to ~/.ssh/keys/{name}
            app(SshKeySyncService::class)->syncKey($model);
        });

        static::deleted(function ($model) {
            // Remove key files from ~/.ssh/keys/{name}
            app(SshKeySyncService::class)->deleteKeyFiles($model->name);
        });
    }
}
