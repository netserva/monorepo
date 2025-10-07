<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemStatsOverview extends StatsOverviewWidget
{
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

            Stat::make('Database', 'SQLite')
                ->description('Development database engine')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('warning'),

            Stat::make('Environment', 'Development')
                ->description('Laravel 12 + Filament 4')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('gray'),
        ];
    }
}
