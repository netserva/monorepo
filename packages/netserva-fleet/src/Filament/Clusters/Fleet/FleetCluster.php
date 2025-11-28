<?php

namespace NetServa\Fleet\Filament\Clusters\Fleet;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Fleet Cluster
 *
 * Groups core fleet infrastructure resources:
 * - Venues (data centers, locations)
 * - VSites (site groups)
 * - VNodes (server nodes)
 * - VHosts (virtual hosts)
 */
class FleetCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Fleet';

    protected static ?int $navigationSort = 10;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Fleet';
    }
}
