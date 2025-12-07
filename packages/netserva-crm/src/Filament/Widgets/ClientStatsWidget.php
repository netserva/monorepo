<?php

declare(strict_types=1);

namespace NetServa\Crm\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Models\CrmClient;

class ClientStatsWidget extends StatsOverviewWidget
{
    // Filament v4: pollingInterval is non-static in parent class
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = CrmClient::count();
        $active = CrmClient::active()->count();
        $prospects = CrmClient::prospect()->count();
        $business = CrmClient::business()->count();
        $personal = CrmClient::personal()->count();

        $stats = [
            Stat::make('Total Clients', $total)
                ->description($active.' active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary'),

            Stat::make('Active', $active)
                ->description(number_format($total > 0 ? ($active / $total) * 100 : 0, 0).'% of total')
                ->color('success'),

            Stat::make('Prospects', $prospects)
                ->description('Potential clients')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),

            Stat::make('Business', $business)
                ->description($personal.' personal')
                ->color('info'),
        ];

        // Add integration stats if available
        if (CrmServiceProvider::hasFleetIntegration()) {
            $vsitesWithClient = \NetServa\Fleet\Models\FleetVsite::whereNotNull('customer_id')->count();
            $stats[] = Stat::make('Assigned VSites', $vsitesWithClient)
                ->description('Linked to clients')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('success');
        }

        if (CrmServiceProvider::hasDomainIntegration()) {
            $domainsWithClient = \App\Models\SwDomain::whereNotNull('customer_id')->count();
            $stats[] = Stat::make('Assigned Domains', $domainsWithClient)
                ->description('Linked to clients')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success');
        }

        return $stats;
    }

    public static function canView(): bool
    {
        return true;
    }
}
