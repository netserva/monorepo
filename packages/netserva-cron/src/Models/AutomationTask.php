<?php

namespace NetServa\Cron\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationTask extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Ns\Automation\Database\Factories\AutomationTaskFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'task_type',
        'command',
        'target_host',
        'target_user',
        'timeout_seconds',
        'max_retries',
        'retry_delay_seconds',
        'is_active',
        'status',
        'priority',
        'success_rate',
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
        'max_retries' => 'integer',
        'retry_delay_seconds' => 'integer',
        'priority' => 'integer',
        'success_rate' => 'decimal:2',
    ];

    // Task types
    const TASK_TYPES = [
        'shell' => 'Shell Command',
        'ssh' => 'SSH Command',
        'script' => 'Script',
    ];

    // Task statuses
    const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    // Priority levels
    const PRIORITIES = [
        1 => 'Low',
        2 => 'Normal',
        3 => 'High',
    ];

    // Relationships
    public function executions(): HasMany
    {
        return $this->hasMany(AutomationJob::class, 'automation_task_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('task_type', $type);
    }

    public function scopeByPriority(Builder $query, int $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'danger',
            default => 'gray',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Unknown';
    }

    public function getTaskTypeLabelAttribute(): string
    {
        return self::TASK_TYPES[$this->task_type] ?? $this->task_type;
    }

    public function getCanExecuteAttribute(): bool
    {
        return $this->is_active && $this->status === 'active';
    }

    // Business logic methods
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    // Static helper methods
    public static function getTaskTypeOptions(): array
    {
        return self::TASK_TYPES;
    }

    public static function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public static function getPriorityOptions(): array
    {
        return self::PRIORITIES;
    }
}
