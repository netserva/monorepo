<?php

namespace NetServa\Mail\Filament\Clusters\Mail;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Mail Cluster
 *
 * Groups mail service resources:
 * - Mail Domains
 * - Mailboxes
 * - Mail Aliases
 * - Mail Queue
 * - Mail Servers
 * - Mail Logs
 */
class MailCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mail';

    protected static ?int $navigationSort = 30;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getClusterBreadcrumb(): string
    {
        return 'Mail';
    }
}
