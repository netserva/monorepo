<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupSnapshot extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\BackupSnapshotFactory::new();
    }

    protected $fillable = [
        'snapshot_id',
        'name',
        'backup_job_id',
        'backup_repository_id',
        'status',
        'backup_type',
        'started_at',
        'completed_at',
        'runtime_seconds',
        'storage_path',
        'storage_filename',
        'original_size_bytes',
        'stored_size_bytes',
        'checksum_value',
        'is_encrypted',
        'parent_snapshot_id',
        'error_message',
        'expires_at',
        'created_by',
        'trigger_type',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'runtime_seconds' => 'integer',
        'original_size_bytes' => 'integer',
        'stored_size_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Snapshot statuses (simplified)
    const STATUSES = [
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    // Backup types (simplified)
    const BACKUP_TYPES = [
        'full' => 'Full Backup',
        'incremental' => 'Incremental',
    ];

    // Trigger types (simplified)
    const TRIGGER_TYPES = [
        'scheduled' => 'Scheduled',
        'manual' => 'Manual',
    ];

    // Relationships
    public function backupJob(): BelongsTo
    {
        return $this->belongsTo(BackupJob::class);
    }

    public function backupRepository(): BelongsTo
    {
        return $this->belongsTo(BackupRepository::class);
    }

    public function parentSnapshot(): BelongsTo
    {
        return $this->belongsTo(BackupSnapshot::class, 'parent_snapshot_id');
    }

    public function childSnapshots(): HasMany
    {
        return $this->hasMany(BackupSnapshot::class, 'parent_snapshot_id');
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('backup_type', $type);
    }

    public function scopeFullBackups(Builder $query): Builder
    {
        return $query->where('backup_type', 'full');
    }

    public function scopeIncrementalBackups(Builder $query): Builder
    {
        return $query->where('backup_type', 'incremental');
    }

    public function scopeByTrigger(Builder $query, string $trigger): Builder
    {
        return $query->where('trigger_type', $trigger);
    }

    public function scopeEligibleForDeletion(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '<', now());
        });
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'running' => 'info',
            'pending' => 'warning',
            'partial' => 'warning',
            'failed', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->stored_size_bytes ?? $this->original_size_bytes ?? 0);
    }

    public function getFormattedOriginalSizeAttribute(): string
    {
        return $this->formatBytes($this->original_size_bytes ?? 0);
    }

    public function getFormattedRuntimeAttribute(): string
    {
        if (! $this->runtime_seconds) {
            return 'N/A';
        }

        $hours = floor($this->runtime_seconds / 3600);
        $minutes = floor(($this->runtime_seconds % 3600) / 60);
        $seconds = $this->runtime_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getCanDeleteAttribute(): bool
    {
        return (! $this->expires_at || $this->expires_at->isPast()) &&
               ! $this->hasIncrementalChildren();
    }

    // Business logic methods
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }

    public function hasIncrementalChildren(): bool
    {
        return $this->backup_type === 'full' && $this->childSnapshots()->exists();
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(array $data = []): void
    {
        $updateData = array_merge([
            'status' => 'completed',
            'completed_at' => now(),
            'runtime_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ], $data);

        $this->update($updateData);

        // Update repository statistics
        $this->backupRepository->recordBackupCompletion(true, $this->stored_size_bytes ?? 0);

        // Record job execution
        $this->backupJob->recordExecution(
            true,
            $this->runtime_seconds,
            $this->stored_size_bytes,
            null
        );
    }

    public function fail(string $errorMessage, array $errorDetails = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'runtime_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
        ]);

        // Update repository statistics
        $this->backupRepository->recordBackupCompletion(false);

        // Record job execution
        $this->backupJob->recordExecution(false, $this->runtime_seconds, null, $errorMessage);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'runtime_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
            'error_message' => $reason ?? 'Cancelled by user',
        ]);
    }

    public function calculateChecksum(string $algorithm = 'sha256'): ?string
    {
        // Basic checksum calculation for verification
        if (! $this->storage_path || ! $this->isSuccessful()) {
            return null;
        }

        $checksum = hash($algorithm, $this->storage_path.$this->id.now()->timestamp);
        $this->update(['checksum_value' => $checksum]);

        return $checksum;
    }

    public function getFullStoragePath(): string
    {
        return $this->backupRepository->storage_path.'/'.ltrim($this->storage_path, '/');
    }

    public function estimateRestoreTime(): int
    {
        // Simple estimate based on size
        if (! $this->stored_size_bytes) {
            return 0;
        }

        // Assume 50MB/s restore speed
        $baselineSpeed = 50 * 1024 * 1024;

        return (int) ceil($this->stored_size_bytes / $baselineSpeed);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    // Static helper methods
    public static function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public static function getBackupTypeOptions(): array
    {
        return self::BACKUP_TYPES;
    }

    public static function getTriggerTypeOptions(): array
    {
        return self::TRIGGER_TYPES;
    }
}
