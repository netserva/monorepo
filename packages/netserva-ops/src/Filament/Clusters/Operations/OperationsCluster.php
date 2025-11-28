<?php

namespace NetServa\Ops\Filament\Clusters\Operations;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Operations Cluster
 *
 * Groups operations and monitoring resources:
 * - Monitoring Checks
 * - Alert Rules
 * - Incidents
 * - Backup Jobs
 * - Backup Repositories
 * - Backup Snapshots
 * - Status Pages
 * - Analytics Dashboards
 * - Analytics Metrics
 * - Analytics Alerts
 * - Automation Jobs (merged from Cron)
 * - Automation Tasks (merged from Cron)
 */
class OperationsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Operations';

    protected static ?int $navigationSort = 50;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Operations';
    }
}
