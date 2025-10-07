<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BackupRepository extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\BackupRepositoryFactory::new();
    }

    protected $table = 'backup_repositories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'storage_driver',
        'storage_path',
        'is_active',
        'is_default',
        'retention_days',
        'encryption_enabled',
        'total_size_bytes',
        'total_snapshots',
        'last_backup_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'retention_days' => 'integer',
        'encryption_enabled' => 'boolean',
        'total_size_bytes' => 'integer',
        'total_snapshots' => 'integer',
        'last_backup_at' => 'datetime',
    ];

    // Storage driver types (simplified)
    const STORAGE_DRIVERS = [
        'local' => 'Local Storage',
        's3' => 'Amazon S3',
        'sftp' => 'SFTP/SSH',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('name') && empty($model->getOriginal('slug'))) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // Relationships
    public function backupJobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }

    public function backupSnapshots(): HasMany
    {
        return $this->hasMany(BackupSnapshot::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeByDriver(Builder $query, string $driver): Builder
    {
        return $query->where('storage_driver', $driver);
    }

    // Accessors
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->total_size_bytes);
    }

    // Business logic methods
    public function canAcceptBackup(): bool
    {
        return $this->is_active;
    }

    public function recordBackupCompletion(bool $success, int $sizeBytes = 0): void
    {
        $data = [
            'last_backup_at' => now(),
        ];

        if ($success) {
            $data['total_size_bytes'] = $this->total_size_bytes + $sizeBytes;
            $data['total_snapshots'] = $this->total_snapshots + 1;
        }

        $this->update($data);
    }

    public function updateStorageStats(): void
    {
        $stats = $this->backupSnapshots()
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as snapshot_count,
                COALESCE(SUM(stored_size_bytes), 0) as total_size
            ')
            ->first();

        $this->update([
            'total_snapshots' => $stats->snapshot_count ?? 0,
            'total_size_bytes' => $stats->total_size ?? 0,
        ]);
    }

    public function shouldEncrypt(): bool
    {
        return $this->encryption_enabled;
    }

    public function makeDefault(): void
    {
        // Remove default flag from other repositories
        static::where('is_default', true)->update(['is_default' => false]);

        // Set this repository as default
        $this->update(['is_default' => true]);
    }

    public function cleanupExpiredSnapshots(): int
    {
        $deleted = 0;
        $cutoffDate = now()->subDays($this->retention_days);

        $expiredSnapshots = $this->backupSnapshots()
            ->where('status', 'completed')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        foreach ($expiredSnapshots as $snapshot) {
            try {
                $snapshot->delete();
                $deleted++;
            } catch (\Exception $e) {
                logger()->error("Failed to delete snapshot {$snapshot->id}: ".$e->getMessage());
            }
        }

        return $deleted;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    // Static helper methods
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    public static function getStorageDriverOptions(): array
    {
        return self::STORAGE_DRIVERS;
    }
}
