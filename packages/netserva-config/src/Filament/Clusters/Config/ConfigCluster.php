<?php

namespace NetServa\Config\Filament\Clusters\Config;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Config Cluster
 *
 * Groups configuration management resources:
 * - Config Templates
 * - Config Profiles
 * - Config Variables
 * - Config Deployments
 * - Secrets
 * - Secret Access
 * - Databases
 * - Database Connections
 * - Database Credentials
 */
class ConfigCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Config';

    protected static ?int $navigationSort = 60;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Config';
    }
}
