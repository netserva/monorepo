<?php

namespace NetServa\Core\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

/**
 * NetServa Core DateTime Column
 *
 * Standardized datetime columns with consistent formatting and labeling.
 * Part of the NetServa Core foundation package.
 */
class DateTimeColumn
{
    /**
     * Create a standardized created_at column
     */
    public static function createdAt(string $name = 'created_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Created')
            ->dateTime('M j, Y g:i A')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Unknown')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a standardized updated_at column
     */
    public static function updatedAt(string $name = 'updated_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Updated')
            ->dateTime('M j, Y g:i A')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Unknown')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a "time ago" style column
     */
    public static function timeAgo(string $name, ?string $label = null): TextColumn
    {
        return TextColumn::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->since()
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Unknown')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a last connected column
     */
    public static function lastConnected(string $name = 'last_connected_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Last Connected')
            ->since()
            ->placeholder('Never')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Never connected')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a last tested column
     */
    public static function lastTested(string $name = 'last_tested_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Last Tested')
            ->since()
            ->placeholder('Never')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Never tested')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a last used column
     */
    public static function lastUsed(string $name = 'last_used_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Last Used')
            ->since()
            ->placeholder('Never')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Never used')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create an expires at column with color coding
     */
    public static function expiresAt(string $name = 'expires_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Expires')
            ->dateTime('M j, Y')
            ->color(function ($state): string {
                if (! $state) {
                    return 'gray';
                }

                $daysUntilExpiry = now()->diffInDays($state, false);

                return match (true) {
                    $daysUntilExpiry < 0 => 'danger',      // Expired
                    $daysUntilExpiry <= 7 => 'warning',    // Expires within a week
                    $daysUntilExpiry <= 30 => 'warning',   // Expires within a month
                    default => 'success',                  // Good for now
                };
            })
            ->tooltip(function ($state): string {
                if (! $state) {
                    return 'No expiration date';
                }

                $daysUntilExpiry = now()->diffInDays($state, false);

                if ($daysUntilExpiry < 0) {
                    return 'Expired '.abs($daysUntilExpiry).' days ago';
                }

                return 'Expires in '.$daysUntilExpiry.' days';
            })
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a scheduled at column
     */
    public static function scheduledAt(string $name = 'scheduled_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Scheduled')
            ->dateTime('M j, Y g:i A')
            ->placeholder('Not scheduled')
            ->color(function ($state): string {
                if (! $state) {
                    return 'gray';
                }

                return $state->isFuture() ? 'warning' : 'success';
            })
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Not scheduled')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a completed at column
     */
    public static function completedAt(string $name = 'completed_at'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Completed')
            ->dateTime('M j, Y g:i A')
            ->placeholder('Not completed')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? 'Not completed')
            ->sortable()
            ->toggleable();
    }

    /**
     * Create a custom datetime column with flexible options
     */
    public static function custom(
        string $name,
        ?string $label = null,
        string $format = 'M j, Y g:i A',
        ?string $placeholder = null,
        bool $sortable = true,
        bool $toggleable = true
    ): TextColumn {
        return TextColumn::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->dateTime($format)
            ->placeholder($placeholder ?? 'Not set')
            ->tooltip(fn ($state): string => $state?->format('l, F j, Y \a\t g:i:s A T') ?? ($placeholder ?? 'Not set'))
            ->sortable($sortable)
            ->toggleable($toggleable);
    }
}
