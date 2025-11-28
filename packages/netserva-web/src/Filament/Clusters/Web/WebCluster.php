<?php

namespace NetServa\Web\Filament\Clusters\Web;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Web Cluster
 *
 * Groups web service resources:
 * - Web Servers (nginx)
 * - Virtual Hosts
 * - Web Applications
 * - SSL Certificates
 * - SSL Certificate Deployments
 */
class WebCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    protected static ?string $navigationLabel = 'Web';

    protected static ?int $navigationSort = 40;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Web';
    }
}
