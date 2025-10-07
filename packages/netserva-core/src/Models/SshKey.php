<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;

/**
 * NetServa Core SSH Key Model
 *
 * Represents an SSH key pair used for authentication.
 * Part of the NetServa Core foundation package.
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
        'ssh_host_id',
        'name',
        'key_type',
        'key_size',
        'file_path',
        'public_key_path',
        'public_key_content',
        'private_key',
        'fingerprint_sha256',
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

    /**
     * Default attribute values
     */
    protected $attributes = [
        'key_type' => 'ed25519',
        'is_active' => true,
        'has_passphrase' => false,
    ];

    /**
     * Get the SSH host this key belongs to
     */
    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(SshHost::class);
    }

    /**
     * Get the SSH connections using this key
     */
    public function sshConnections(): HasMany
    {
        return $this->hasMany(SshConnection::class);
    }

    /**
     * Get the full path to the private key file
     */
    public function getPrivateKeyFullPathAttribute(): string
    {
        $homeDir = env('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['dir'] : '/home/'.get_current_user());

        return str_replace('~', $homeDir, $this->file_path);
    }

    /**
     * Get the full path to the public key file
     */
    public function getPublicKeyFullPathAttribute(): string
    {
        $homeDir = env('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['dir'] : '/home/'.get_current_user());

        return str_replace('~', $homeDir, $this->public_key_path);
    }

    /**
     * Check if key files exist on filesystem
     */
    public function filesExist(): bool
    {
        return File::exists($this->private_key_full_path) &&
               File::exists($this->public_key_full_path);
    }

    /**
     * Get the short fingerprint for display
     */
    public function getShortFingerprintAttribute(): string
    {
        if (! $this->fingerprint_sha256) {
            return 'Unknown';
        }

        // Extract the last 16 characters for a short fingerprint
        return '...'.substr($this->fingerprint_sha256, -16);
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
        $type = strtoupper($this->key_type);

        if ($this->key_size && $this->key_type !== 'ed25519') {
            return "{$type} {$this->key_size}";
        }

        return $type;
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
     * Generate a display name for this key
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "Key {$this->id}";
    }

    /**
     * Get the comment or generate a default one
     */
    public function getEffectiveCommentAttribute(): string
    {
        if ($this->comment) {
            return $this->comment;
        }

        $host = $this->sshHost ? $this->sshHost->host : 'unknown';

        return "NetServa key for {$host}";
    }

    /**
     * Validate the private key content
     */
    public function validatePrivateKey(): bool
    {
        if (! $this->private_key) {
            return false;
        }

        try {
            // Try to load the private key to validate it
            \phpseclib3\Crypt\PublicKeyLoader::load($this->private_key);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate public key from private key if missing
     */
    public function generatePublicKey(): bool
    {
        if (! $this->private_key) {
            return false;
        }

        try {
            $privateKey = \phpseclib3\Crypt\PublicKeyLoader::load($this->private_key);
            $publicKey = $privateKey->getPublicKey();

            $this->update([
                'public_key_content' => (string) $publicKey,
                'fingerprint_sha256' => 'SHA256:'.$publicKey->getFingerprint('sha256'),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Save key to filesystem
     */
    public function saveToFilesystem(): bool
    {
        if (! $this->private_key || ! $this->file_path) {
            return false;
        }

        try {
            $privateKeyPath = $this->private_key_full_path;
            $publicKeyPath = $this->public_key_full_path;

            // Ensure directory exists
            $dir = dirname($privateKeyPath);
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0700, true);
            }

            // Write private key
            File::put($privateKeyPath, $this->private_key);
            chmod($privateKeyPath, 0600);

            // Write public key if we have it
            if ($this->public_key_content) {
                File::put($publicKeyPath, $this->public_key_content);
                chmod($publicKeyPath, 0644);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load key from filesystem
     */
    public function loadFromFilesystem(): bool
    {
        if (! $this->file_path || ! $this->filesExist()) {
            return false;
        }

        try {
            $privateKeyContent = File::get($this->private_key_full_path);
            $publicKeyContent = File::get($this->public_key_full_path);

            $this->update([
                'private_key' => $privateKeyContent,
                'public_key_content' => $publicKeyContent,
            ]);

            // Generate fingerprint if missing
            if (! $this->fingerprint_sha256) {
                $this->generatePublicKey();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
