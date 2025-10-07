<?php

namespace NetServa\Core\Enums;

/**
 * NetServa Core Priority Enum
 *
 * Standardized priority levels for use across the NetServa ecosystem.
 * Part of the NetServa Core foundation package.
 */
enum Priority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case CRITICAL = 'critical';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
            self::URGENT => 'Urgent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LOW => 'Low priority - can be addressed when convenient',
            self::NORMAL => 'Normal priority - standard processing',
            self::HIGH => 'High priority - should be addressed soon',
            self::CRITICAL => 'Critical priority - requires immediate attention',
            self::URGENT => 'Urgent priority - emergency response required',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::NORMAL => 'primary',
            self::HIGH => 'warning',
            self::CRITICAL => 'danger',
            self::URGENT => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LOW => 'heroicon-s-arrow-down',
            self::NORMAL => 'heroicon-s-minus',
            self::HIGH => 'heroicon-s-arrow-up',
            self::CRITICAL => 'heroicon-s-exclamation-triangle',
            self::URGENT => 'heroicon-s-fire',
        };
    }

    public function numericValue(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
            self::URGENT => 5,
        };
    }

    public function isHigh(): bool
    {
        return in_array($this, [
            self::HIGH,
            self::CRITICAL,
            self::URGENT,
        ]);
    }

    public function isCritical(): bool
    {
        return in_array($this, [
            self::CRITICAL,
            self::URGENT,
        ]);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function highPriorities(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isHigh())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function criticalPriorities(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isCritical())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function fromNumeric(int $value): self
    {
        return match ($value) {
            1 => self::LOW,
            2 => self::NORMAL,
            3 => self::HIGH,
            4 => self::CRITICAL,
            5 => self::URGENT,
            default => self::NORMAL,
        };
    }
}
