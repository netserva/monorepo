<?php

namespace NetServa\Core\Enums;

/**
 * NetServa Core Status Enum
 *
 * General-purpose status enum for use across the NetServa ecosystem.
 * Part of the NetServa Core foundation package.
 */
enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case PROCESSING = 'processing';
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case ERROR = 'error';
    case WARNING = 'warning';
    case SUCCESS = 'success';
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING => 'Pending',
            self::FAILED => 'Failed',
            self::SUSPENDED => 'Suspended',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::PROCESSING => 'Processing',
            self::QUEUED => 'Queued',
            self::RUNNING => 'Running',
            self::STOPPED => 'Stopped',
            self::ERROR => 'Error',
            self::WARNING => 'Warning',
            self::SUCCESS => 'Success',
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE, self::RUNNING, self::SUCCESS, self::COMPLETED, self::PUBLISHED => 'success',
            self::INACTIVE, self::STOPPED, self::CANCELLED, self::ARCHIVED => 'gray',
            self::PENDING, self::PROCESSING, self::QUEUED, self::WARNING, self::SUSPENDED, self::DRAFT => 'warning',
            self::FAILED, self::ERROR => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE, self::SUCCESS, self::COMPLETED => 'heroicon-s-check-circle',
            self::INACTIVE => 'heroicon-s-pause-circle',
            self::PENDING, self::QUEUED => 'heroicon-s-clock',
            self::FAILED, self::ERROR => 'heroicon-s-x-circle',
            self::SUSPENDED => 'heroicon-s-pause',
            self::CANCELLED => 'heroicon-s-stop-circle',
            self::PROCESSING, self::RUNNING => 'heroicon-s-play-circle',
            self::STOPPED => 'heroicon-s-stop',
            self::WARNING => 'heroicon-s-exclamation-triangle',
            self::DRAFT => 'heroicon-s-document',
            self::PUBLISHED => 'heroicon-s-eye',
            self::ARCHIVED => 'heroicon-s-archive-box',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::ACTIVE,
            self::RUNNING,
            self::PROCESSING,
            self::PUBLISHED,
        ]);
    }

    public function isComplete(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::SUCCESS,
            self::FAILED,
            self::CANCELLED,
            self::ERROR,
        ]);
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::ACTIVE,
            self::SUCCESS,
            self::COMPLETED,
        ]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::ERROR,
        ]);
    }

    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::QUEUED,
            self::PROCESSING,
            self::RUNNING,
        ]);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function activeStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isActive())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function completedStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isComplete())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function pendingStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isPending())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
