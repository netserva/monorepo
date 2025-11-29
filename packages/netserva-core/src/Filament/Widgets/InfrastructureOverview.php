<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Infrastructure Overview Widget
 *
 * Displays key infrastructure metrics across all NetServa subsystems
 */
class InfrastructureOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('Total VSites', $this->getVSitesCount())
                ->description('Hosting providers/locations')
                ->descriptionIcon('heroicon-m-building-office')
                ->chart($this->getVSitesTrend())
                ->color('success'),

            Stat::make('Active VNodes', $this->getVNodesCount())
                ->description('Physical/virtual servers')
                ->descriptionIcon('heroicon-m-server')
                ->chart($this->getVNodesTrend())
                ->color('info'),

            Stat::make('Managed VHosts', $this->getVHostsCount())
                ->description('Hosted domains/applications')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->chart($this->getVHostsTrend())
                ->color('warning'),

            Stat::make('DNS Zones', $this->getDnsZonesCount())
                ->description('Managed DNS zones')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('primary'),

            Stat::make('Mail Domains', $this->getMailDomainsCount())
                ->description('Email hosting domains')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('success'),

            Stat::make('SSL Certificates', $this->getSslCertificatesCount())
                ->description($this->getSslExpiryWarning())
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($this->getSslStatusColor()),
        ];
    }

    protected function getVSitesCount(): int
    {
        if (! class_exists(\NetServa\Fleet\Models\FleetVsite::class)) {
            return 0;
        }

        return \NetServa\Fleet\Models\FleetVsite::count();
    }

    protected function getVNodesCount(): int
    {
        if (! class_exists(\NetServa\Fleet\Models\FleetVnode::class)) {
            return 0;
        }

        return \NetServa\Fleet\Models\FleetVnode::count();
    }

    protected function getVHostsCount(): int
    {
        if (! class_exists(\NetServa\Fleet\Models\FleetVhost::class)) {
            return 0;
        }

        return \NetServa\Fleet\Models\FleetVhost::count();
    }

    protected function getDnsZonesCount(): int
    {
        if (! class_exists(\NetServa\Dns\Models\DnsZone::class)) {
            return 0;
        }

        return \NetServa\Dns\Models\DnsZone::count();
    }

    protected function getMailDomainsCount(): int
    {
        if (! class_exists(\NetServa\Mail\Models\MailDomain::class)) {
            return 0;
        }

        return \NetServa\Mail\Models\MailDomain::where('active', true)->count();
    }

    protected function getSslCertificatesCount(): int
    {
        if (! class_exists(\NetServa\Web\Models\SslCertificate::class)) {
            return 0;
        }

        return \NetServa\Web\Models\SslCertificate::where('status', 'active')->count();
    }

    protected function getSslExpiryWarning(): string
    {
        if (! class_exists(\NetServa\Web\Models\SslCertificate::class)) {
            return 'No expiring certificates';
        }

        $expiringSoon = \NetServa\Web\Models\SslCertificate::where('status', 'active')
            ->where('not_after', '<=', now()->addDays(30))
            ->count();

        if ($expiringSoon > 0) {
            return "{$expiringSoon} expiring within 30 days";
        }

        return 'All certificates valid';
    }

    protected function getSslStatusColor(): string
    {
        if (! class_exists(\NetServa\Web\Models\SslCertificate::class)) {
            return 'gray';
        }

        $expiringSoon = \NetServa\Web\Models\SslCertificate::where('status', 'active')
            ->where('not_after', '<=', now()->addDays(30))
            ->count();

        return $expiringSoon > 0 ? 'danger' : 'success';
    }

    protected function getVSitesTrend(): array
    {
        return $this->generateTrend(\NetServa\Fleet\Models\FleetVsite::class);
    }

    protected function getVNodesTrend(): array
    {
        return $this->generateTrend(\NetServa\Fleet\Models\FleetVnode::class);
    }

    protected function getVHostsTrend(): array
    {
        return $this->generateTrend(\NetServa\Fleet\Models\FleetVhost::class);
    }

    protected function generateTrend(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [0, 0, 0, 0, 0, 0, 0];
        }

        $counts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $counts[] = $modelClass::where('created_at', '<=', $date)->count();
        }

        return $counts;
    }
}
