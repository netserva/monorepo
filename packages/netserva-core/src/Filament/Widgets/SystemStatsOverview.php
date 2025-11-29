<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * System Statistics Overview Widget
 *
 * Displays key system status metrics on the dashboard
 */
class SystemStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('System Status', 'Online')
                ->description('NS infrastructure is running')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Plugin Manager', 'Active')
                ->description('Plugin architecture enabled')
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color('info'),

            Stat::make('Database', $this->getDatabaseEngine())
                ->description($this->getDatabaseDescription())
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('warning'),

            Stat::make('Environment', config('app.env', 'production'))
                ->description('Laravel '.app()->version().' + Filament 4')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('gray'),
        ];
    }

    protected function getDatabaseEngine(): string
    {
        $connection = config('database.default');

        return match ($connection) {
            'sqlite' => 'SQLite',
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'mariadb' => 'MariaDB',
            default => ucfirst($connection),
        };
    }

    protected function getDatabaseDescription(): string
    {
        $connection = config('database.default');

        return match ($connection) {
            'sqlite' => 'Development database engine',
            'mysql', 'mariadb' => 'Production database engine',
            'pgsql' => 'PostgreSQL database engine',
            default => 'Database connection active',
        };
    }
}
