<?php

namespace NetServa\Core\Filament\Tables\Columns;

use Filament\Tables\Columns\IconColumn;

/**
 * NetServa Core Status Column
 *
 * A standardized status column for displaying boolean status with consistent icons and colors.
 * Part of the NetServa Core foundation package.
 */
class StatusColumn
{
    /**
     * Create a standardized active/inactive status column
     */
    public static function make(string $name = 'is_active'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Status')
            ->boolean()
            ->trueIcon('heroicon-s-check-circle')
            ->falseIcon('heroicon-s-x-circle')
            ->trueColor('success')
            ->falseColor('danger')
            ->tooltip(fn ($state): string => $state ? 'Active' : 'Inactive')
            ->sortable();
    }

    /**
     * Create a reachability status column for network resources
     */
    public static function reachable(string $name = 'is_reachable'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Reachable')
            ->boolean()
            ->trueIcon('heroicon-s-signal')
            ->falseIcon('heroicon-s-signal-slash')
            ->trueColor('success')
            ->falseColor('danger')
            ->tooltip(fn ($state): string => match ($state) {
                true => 'Reachable',
                false => 'Unreachable',
                default => 'Unknown',
            })
            ->sortable();
    }

    /**
     * Create an enabled/disabled status column
     */
    public static function enabled(string $name = 'enabled'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Enabled')
            ->boolean()
            ->trueIcon('heroicon-s-check-circle')
            ->falseIcon('heroicon-s-pause-circle')
            ->trueColor('success')
            ->falseColor('warning')
            ->tooltip(fn ($state): string => $state ? 'Enabled' : 'Disabled')
            ->sortable();
    }

    /**
     * Create a connection status column
     */
    public static function connected(string $name = 'is_connected'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Connected')
            ->boolean()
            ->trueIcon('heroicon-s-link')
            ->falseIcon('heroicon-s-link-slash')
            ->trueColor('success')
            ->falseColor('gray')
            ->tooltip(fn ($state): string => $state ? 'Connected' : 'Disconnected')
            ->sortable();
    }

    /**
     * Create a verified status column
     */
    public static function verified(string $name = 'is_verified'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Verified')
            ->boolean()
            ->trueIcon('heroicon-s-shield-check')
            ->falseIcon('heroicon-s-shield-exclamation')
            ->trueColor('success')
            ->falseColor('warning')
            ->tooltip(fn ($state): string => $state ? 'Verified' : 'Unverified')
            ->sortable();
    }

    /**
     * Create a secure/insecure status column
     */
    public static function secure(string $name = 'is_secure'): IconColumn
    {
        return IconColumn::make($name)
            ->label('Secure')
            ->boolean()
            ->trueIcon('heroicon-s-lock-closed')
            ->falseIcon('heroicon-s-lock-open')
            ->trueColor('success')
            ->falseColor('danger')
            ->tooltip(fn ($state): string => $state ? 'Secure' : 'Insecure')
            ->sortable();
    }
}
