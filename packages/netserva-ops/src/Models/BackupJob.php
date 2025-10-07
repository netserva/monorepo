<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use NetServa\Core\Models\InfrastructureNode;

class BackupJob extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\BackupJobFactory::new();
    }

    protected $fillable = [
        'job_id',
        'name',
        'description',
        'target_host',
        'source_paths',
        'destination_path',
        'backup_type',
        'exclude_patterns',
        'enabled',
        'schedule',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'backup_size_bytes',
        'retention_days',
        'backup_repository_id',
    ];

    protected $casts = [
        'source_paths' => 'array',
        'exclude_patterns' => 'array',
        'enabled' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'backup_size_bytes' => 'integer',
        'retention_days' => 'integer',
    ];

    // Backup types (simplified)
    const BACKUP_TYPES = [
        'database' => 'Database Backup',
        'files' => 'File System Backup',
        'system' => 'System Configuration Backup',
    ];

    // Relationships
    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function backupRepository(): BelongsTo
    {
        return $this->belongsTo(BackupRepository::class);
    }

    public function backupSnapshots(): HasMany
    {
        return $this->hasMany(BackupSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(BackupSnapshot::class)->latestOfMany();
    }

    public function lastSuccessfulSnapshot(): HasOne
    {
        return $this->hasOne(BackupSnapshot::class)
            ->where('status', 'completed')
            ->latestOfMany();
    }

    public function runningSnapshots(): HasMany
    {
        return $this->hasMany(BackupSnapshot::class)
            ->whereIn('status', ['pending', 'running']);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('backup_type', $type);
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'running' => 'info',
            'inactive' => 'gray',
            'failed' => 'danger',
            default => 'success',
        };
    }

    // Business logic methods
    public function isRunning(): bool
    {
        return $this->runningSnapshots()->exists();
    }

    public function canRun(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->isRunning()) {
            return false;
        }

        if (! $this->backupRepository || ! $this->backupRepository->canAcceptBackup()) {
            return false;
        }

        return true;
    }

    public function recordExecution(bool $success, ?int $runtimeSeconds = null, ?int $sizeBytes = null, ?string $errorMessage = null): void
    {
        $data = [
            'status' => $success ? 'completed' : 'failed',
            'completed_at' => now(),
        ];

        if ($runtimeSeconds) {
            $data['duration_seconds'] = $runtimeSeconds;
        }

        if ($sizeBytes) {
            $data['backup_size_bytes'] = $sizeBytes;
        }

        $this->update($data);
    }

    // Accessors
    public function getSourcesAttribute(): array
    {
        $paths = $this->source_paths;
        if (is_string($paths)) {
            return json_decode($paths, true) ?? [];
        }

        return is_array($paths) ? $paths : [];
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->enabled ?? true;
    }

    public function getDestinationFullPath(): string
    {
        $basePath = $this->backupRepository->storage_path ?? '';
        $jobPath = $this->destination_path ?? '';

        return rtrim($basePath, '/').'/'.ltrim($jobPath, '/');
    }

    public function createSnapshot(string $backupType = 'full'): BackupSnapshot
    {
        return $this->backupSnapshots()->create([
            'snapshot_id' => Str::uuid(),
            'backup_repository_id' => $this->backup_repository_id,
            'backup_type' => $backupType,
            'status' => 'pending',
            'started_at' => now(),
            'storage_path' => $this->generateSnapshotPath(),
            'created_by' => auth()->user()?->name ?? 'System',
            'trigger_type' => 'scheduled',
        ]);
    }

    protected function generateSnapshotPath(): string
    {
        $basePath = $this->getDestinationFullPath();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$this->name}_{$timestamp}";

        return rtrim($basePath, '/').'/'.$filename;
    }

    public function estimateBackupSize(): int
    {
        // Default estimates by backup type (in bytes)
        return match ($this->backup_type) {
            'database' => 100 * 1024 * 1024, // 100MB
            'files' => 1024 * 1024 * 1024, // 1GB
            'system' => 50 * 1024 * 1024, // 50MB
            default => 500 * 1024 * 1024, // 500MB
        };
    }

    // Static helper methods
    public static function getBackupTypeOptions(): array
    {
        return self::BACKUP_TYPES;
    }
}
