<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NetServa\Core\Models\VHost;
use NetServa\Dns\Models\DnsZone;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Mail\Models\MailDomain;
use NetServa\Web\Models\SslCertificate;

/**
 * Infrastructure Overview Widget
 *
 * Displays key infrastructure metrics across all NetServa subsystems
 */
class InfrastructureOverview extends BaseWidget
{
    protected static ?int $sort = 1;

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
        if (! class_exists(FleetVsite::class)) {
            return 0;
        }

        return FleetVsite::count();
    }

    protected function getVNodesCount(): int
    {
        if (! class_exists(FleetVnode::class)) {
            return 0;
        }

        return FleetVnode::count();
    }

    protected function getVHostsCount(): int
    {
        if (! class_exists(FleetVhost::class)) {
            return VHost::count();
        }

        return FleetVhost::count();
    }

    protected function getDnsZonesCount(): int
    {
        if (! class_exists(DnsZone::class)) {
            return 0;
        }

        return DnsZone::count();
    }

    protected function getMailDomainsCount(): int
    {
        if (! class_exists(MailDomain::class)) {
            return 0;
        }

        return MailDomain::where('active', true)->count();
    }

    protected function getSslCertificatesCount(): int
    {
        if (! class_exists(SslCertificate::class)) {
            return 0;
        }

        return SslCertificate::where('status', 'active')->count();
    }

    protected function getSslExpiryWarning(): string
    {
        if (! class_exists(SslCertificate::class)) {
            return 'No expiring certificates';
        }

        $expiringSoon = SslCertificate::where('status', 'active')
            ->where('not_after', '<=', now()->addDays(30))
            ->count();

        if ($expiringSoon > 0) {
            return "{$expiringSoon} expiring within 30 days";
        }

        return 'All certificates valid';
    }

    protected function getSslStatusColor(): string
    {
        if (! class_exists(SslCertificate::class)) {
            return 'gray';
        }

        $expiringSoon = SslCertificate::where('status', 'active')
            ->where('not_after', '<=', now()->addDays(30))
            ->count();

        return $expiringSoon > 0 ? 'danger' : 'success';
    }

    protected function getVSitesTrend(): array
    {
        // Simple trend - last 7 days count
        return $this->generateTrend(FleetVsite::class);
    }

    protected function getVNodesTrend(): array
    {
        return $this->generateTrend(FleetVnode::class);
    }

    protected function getVHostsTrend(): array
    {
        return $this->generateTrend(FleetVhost::class);
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
