<?php

namespace NetServa\Core\Enums;

/**
 * NetServa Core Platform Node Status
 *
 * Standardized platform node statuses across the NetServa ecosystem.
 * Part of the NetServa Core foundation package.
 */
enum PlatformNodeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case PROVISIONING = 'provisioning';
    case DEPLOYING = 'deploying';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case STARTING = 'starting';
    case STOPPING = 'stopping';
    case RESTARTING = 'restarting';
    case FAILED = 'failed';
    case ERROR = 'error';
    case WARNING = 'warning';
    case MAINTENANCE = 'maintenance';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING => 'Pending',
            self::PROVISIONING => 'Provisioning',
            self::DEPLOYING => 'Deploying',
            self::RUNNING => 'Running',
            self::STOPPED => 'Stopped',
            self::STARTING => 'Starting',
            self::STOPPING => 'Stopping',
            self::RESTARTING => 'Restarting',
            self::FAILED => 'Failed',
            self::ERROR => 'Error',
            self::WARNING => 'Warning',
            self::MAINTENANCE => 'Maintenance',
            self::SUSPENDED => 'Suspended',
            self::TERMINATED => 'Terminated',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACTIVE => 'Node is active and operational',
            self::INACTIVE => 'Node is inactive but available',
            self::PENDING => 'Node is pending activation',
            self::PROVISIONING => 'Node is being provisioned',
            self::DEPLOYING => 'Node is being deployed',
            self::RUNNING => 'Node is currently running',
            self::STOPPED => 'Node has been stopped',
            self::STARTING => 'Node is starting up',
            self::STOPPING => 'Node is shutting down',
            self::RESTARTING => 'Node is restarting',
            self::FAILED => 'Node has failed',
            self::ERROR => 'Node has encountered an error',
            self::WARNING => 'Node has warnings that need attention',
            self::MAINTENANCE => 'Node is under maintenance',
            self::SUSPENDED => 'Node has been suspended',
            self::TERMINATED => 'Node has been terminated',
            self::UNKNOWN => 'Node status is unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE, self::RUNNING => 'success',
            self::INACTIVE, self::STOPPED => 'gray',
            self::PENDING, self::PROVISIONING, self::DEPLOYING => 'warning',
            self::STARTING, self::STOPPING, self::RESTARTING => 'info',
            self::FAILED, self::ERROR, self::TERMINATED => 'danger',
            self::WARNING => 'warning',
            self::MAINTENANCE => 'primary',
            self::SUSPENDED => 'warning',
            self::UNKNOWN => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE, self::RUNNING => 'heroicon-s-check-circle',
            self::INACTIVE => 'heroicon-s-pause-circle',
            self::STOPPED => 'heroicon-s-stop-circle',
            self::PENDING => 'heroicon-s-clock',
            self::PROVISIONING, self::DEPLOYING => 'heroicon-s-arrow-up-circle',
            self::STARTING => 'heroicon-s-play-circle',
            self::STOPPING => 'heroicon-s-stop-circle',
            self::RESTARTING => 'heroicon-s-arrow-path',
            self::FAILED, self::ERROR => 'heroicon-s-x-circle',
            self::WARNING => 'heroicon-s-exclamation-triangle',
            self::MAINTENANCE => 'heroicon-s-wrench-screwdriver',
            self::SUSPENDED => 'heroicon-s-pause',
            self::TERMINATED => 'heroicon-s-archive-box-x-mark',
            self::UNKNOWN => 'heroicon-s-question-mark-circle',
        };
    }

    public function isOperational(): bool
    {
        return in_array($this, [
            self::ACTIVE,
            self::RUNNING,
        ]);
    }

    public function isTransitional(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PROVISIONING,
            self::DEPLOYING,
            self::STARTING,
            self::STOPPING,
            self::RESTARTING,
        ]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::ERROR,
            self::TERMINATED,
        ]);
    }

    public function isWarning(): bool
    {
        return in_array($this, [
            self::WARNING,
            self::SUSPENDED,
        ]);
    }

    public function isStable(): bool
    {
        return ! $this->isTransitional();
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function operationalStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isOperational())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function transitionalStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isTransitional())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function failedStatuses(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isFailed())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
