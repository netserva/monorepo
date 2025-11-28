<?php

namespace NetServa\Fleet\Filament\Clusters\Network;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Network Cluster
 *
 * Groups network infrastructure resources:
 * - IP Networks (CIDR blocks)
 * - IP Addresses (allocation tracking)
 * - IP Reservations (reserved ranges)
 * - WireGuard Servers (VPN endpoints)
 * - WireGuard Peers (VPN clients)
 */
class NetworkCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Network';

    protected static ?int $navigationSort = 20;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Network';
    }
}
