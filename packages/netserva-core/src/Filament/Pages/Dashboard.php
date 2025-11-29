<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use NetServa\Core\Filament\Widgets\InfrastructureOverview;
use NetServa\Core\Filament\Widgets\ServiceHealthStatus;
use NetServa\Core\Filament\Widgets\SystemStatsOverview;
use UnitEnum;

/**
 * Core Dashboard Page
 *
 * Provides the main dashboard with system overview widgets.
 * Located within the Core navigation group.
 */
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static string|UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            SystemStatsOverview::class,
            InfrastructureOverview::class,
            ServiceHealthStatus::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
