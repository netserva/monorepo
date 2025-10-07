<?php

namespace NetServa\Cron\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationJob extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Ns\Automation\Database\Factories\AutomationJobFactory::new();
    }

    protected $fillable = [
        'job_name',
        'automation_task_id',
        'status',
        'priority',
        'started_at',
        'completed_at',
        'execution_time_seconds',
        'command_executed',
        'stdout',
        'stderr',
        'exit_code',
        'error_message',
        'target_host',
        'target_user',
        'progress_percent',
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'execution_time_seconds' => 'integer',
        'exit_code' => 'integer',
        'progress_percent' => 'decimal:2',
    ];

    // Job statuses
    const STATUSES = [
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    // Priority levels
    const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
    ];

    // Relationships
    public function automationTask(): BelongsTo
    {
        return $this->belongsTo(AutomationTask::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'running' => 'info',
            'pending' => 'warning',
            'failed', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'success',
            'normal' => 'secondary',
            'high' => 'danger',
            default => 'gray',
        };
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }

    // Business logic methods
    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'progress_percent' => 0,
        ]);
    }

    public function complete(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'execution_time_seconds' => $duration,
            'progress_percent' => 100,
        ]);
    }

    public function fail(string $errorMessage): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'execution_time_seconds' => $duration,
            'error_message' => $errorMessage,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    // Static helper methods
    public static function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public static function getPriorityOptions(): array
    {
        return self::PRIORITIES;
    }
}
