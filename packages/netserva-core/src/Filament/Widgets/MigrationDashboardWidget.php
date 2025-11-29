<?php

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NetServa\Fleet\Models\FleetVhost;

/**
 * Migration Dashboard Widget
 *
 * Provides at-a-glance migration statistics for NetServa 3.0
 *
 * Features:
 * - Migration status breakdown
 * - Success/failure rates
 * - Recent migration activity
 * - Quick action navigation
 */
class MigrationDashboardWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Filament 4.0: pollingInterval is non-static in parent class
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Get migration status counts
        $statusCounts = FleetVhost::selectRaw('migration_status, COUNT(*) as count')
            ->groupBy('migration_status')
            ->pluck('count', 'migration_status')
            ->toArray();

        $native = $statusCounts['native'] ?? 0;
        $discovered = $statusCounts['discovered'] ?? 0;
        $validated = $statusCounts['validated'] ?? 0;
        $migrated = $statusCounts['migrated'] ?? 0;
        $failed = $statusCounts['failed'] ?? 0;
        $total = array_sum($statusCounts);

        // Calculate success rate
        $attempted = $migrated + $failed;
        $successRate = $attempted > 0 ? round(($migrated / $attempted) * 100, 1) : 0;

        // Get recent migration
        $recentMigration = FleetVhost::where('migration_status', 'migrated')
            ->latest('migrated_at')
            ->first();

        $recentMigrationText = $recentMigration
            ? $recentMigration->domain.' ('.($recentMigration->migrated_at?->diffForHumans() ?? 'recently').')'
            : 'None yet';

        return [
            // Total VHosts
            Stat::make('Total VHosts', $total)
                ->description('Across all migration statuses')
                ->descriptionIcon('heroicon-o-server-stack')
                ->color('gray'),

            // Native NS 3.0
            Stat::make('Native NS 3.0', $native)
                ->description('Already running NS 3.0')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success')
                ->url(route('filament.admin.resources.fleet-vhosts.index', [
                    'tableFilters' => ['migration_status' => ['value' => 'native']],
                ])),

            // Discovered
            Stat::make('Discovered', $discovered)
                ->description('Legacy vhosts found')
                ->descriptionIcon('heroicon-o-magnifying-glass')
                ->color('info')
                ->url(route('filament.admin.resources.fleet-vhosts.index', [
                    'tableFilters' => ['migration_status' => ['value' => 'discovered']],
                ])),

            // Validated
            Stat::make('Validated', $validated)
                ->description('Ready for migration')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->url(route('filament.admin.resources.fleet-vhosts.index', [
                    'tableFilters' => ['migration_status' => ['value' => 'validated']],
                ])),

            // Migrated
            Stat::make('Migrated', $migrated)
                ->description('Successfully migrated')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('success')
                ->url(route('filament.admin.resources.fleet-vhosts.index', [
                    'tableFilters' => ['migration_status' => ['value' => 'migrated']],
                ])),

            // Failed
            Stat::make('Failed', $failed)
                ->description('Migration errors')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger')
                ->url(route('filament.admin.resources.fleet-vhosts.index', [
                    'tableFilters' => ['migration_status' => ['value' => 'failed']],
                ])),

            // Success Rate
            Stat::make('Success Rate', $successRate.'%')
                ->description($attempted > 0 ? "{$migrated} of {$attempted} successful" : 'No migrations attempted')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger')),

            // Last Migration
            Stat::make('Last Migration', $recentMigrationText)
                ->description('Most recent migration')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray')
                ->url($recentMigration ? route('filament.admin.resources.fleet-vhosts.view', $recentMigration) : null),
        ];
    }

    public function getColumns(): int
    {
        return 2; // 2 columns on desktop, will stack on mobile
    }
}
