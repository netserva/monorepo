<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mount Point Model
 *
 * Represents SSHFS mount points for accessing remote servers
 * as local filesystems.
 */
class MountPoint extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\MountPointFactory::new();
    }

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'remote_host',
        'remote_path',
        'local_path',
        'mount_type',
        'mount_options',
        'auto_mount',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'mount_options' => 'array',
        'auto_mount' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'unmounted',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mountPoint) {
            // Auto-generate name if not provided
            if (empty($mountPoint->name)) {
                $mountPoint->name = $mountPoint->remote_host ?: 'mount-'.time();
            }

            if (empty($mountPoint->display_name)) {
                $mountPoint->display_name = $mountPoint->name;
            }

            // Auto-generate local_path if not provided
            if (empty($mountPoint->local_path)) {
                $mountPoint->local_path = '/mnt/'.$mountPoint->name;
            }
        });
    }

    /**
     * Check if mount point is currently mounted
     */
    public function isMounted(): bool
    {
        return $this->status === 'mounted';
    }

    /**
     * Check if mount point has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get mounted mount points
     */
    public function scopeMounted($query)
    {
        return $query->where('status', 'mounted');
    }

    /**
     * Get unmounted mount points
     */
    public function scopeUnmounted($query)
    {
        return $query->where('status', 'unmounted');
    }

    /**
     * Get failed mount points
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get auto-mount points
     */
    public function scopeAutoMount($query)
    {
        return $query->where('auto_mount', true);
    }

    /**
     * Get mount points for a specific host
     */
    public function scopeForHost($query, string $host)
    {
        return $query->where('remote_host', $host);
    }

    /**
     * Generate SSHFS mount command
     */
    public function getMountCommand(): string
    {
        $options = $this->getFormattedOptions();
        $optionsString = $options ? " -o {$options}" : '';

        return "sshfs {$this->remote_host}:{$this->remote_path} {$this->local_path}{$optionsString}";
    }

    /**
     * Generate umount command
     */
    public function getUnmountCommand(): string
    {
        return "fusermount -u {$this->local_path}";
    }

    /**
     * Get formatted mount options for SSHFS
     */
    protected function getFormattedOptions(): string
    {
        if (empty($this->mount_options)) {
            return '';
        }

        $formatted = [];
        foreach ($this->mount_options as $key => $value) {
            if (is_numeric($key)) {
                $formatted[] = $value; // Boolean option like 'follow_symlinks'
            } else {
                $formatted[] = "{$key}={$value}"; // Key-value option like 'uid=1000'
            }
        }

        return implode(',', $formatted);
    }

    /**
     * Get default mount options for SSHFS
     */
    public static function getDefaultMountOptions(): array
    {
        return [
            'follow_symlinks',
            'allow_other',
            'reconnect',
            'ServerAliveInterval=15',
            'ServerAliveCountMax=3',
        ];
    }

    /**
     * Create local mount directory if it doesn't exist
     */
    public function ensureLocalDirectory(): bool
    {
        if (! is_dir($this->local_path)) {
            return mkdir($this->local_path, 0755, true);
        }

        return true;
    }

    /**
     * Get mount points ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Virtual attribute for backward compatibility
     */
    public function getTypeAttribute()
    {
        return $this->mount_type;
    }

    /**
     * Virtual attribute for backward compatibility
     */
    public function getHostAttribute()
    {
        return $this->remote_host;
    }

    /**
     * Virtual attribute for backward compatibility
     */
    public function getIsMountedAttribute()
    {
        return $this->status === 'mounted';
    }

    /**
     * Virtual attribute for user (stored in remote_host for SSHFS)
     */
    public function getUserAttribute()
    {
        return $this->user_cache ?? 'user';
    }
}
