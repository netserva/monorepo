<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Service Health Status Widget
 *
 * Displays real-time health status of critical infrastructure services
 */
class ServiceHealthStatus extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $sshHosts = $this->getSshHostsStatus();
        $dnsProviders = $this->getDnsProvidersStatus();
        $mailServers = $this->getMailServersStatus();
        $webServers = $this->getWebServersStatus();

        return [
            Stat::make('SSH Hosts', "{$sshHosts['online']}/{$sshHosts['total']}")
                ->description("{$sshHosts['health_percentage']}% online")
                ->descriptionIcon('heroicon-m-server')
                ->color($this->getHealthColor($sshHosts['health_percentage'])),

            Stat::make('DNS Providers', "{$dnsProviders['active']}/{$dnsProviders['total']}")
                ->description("{$dnsProviders['health_percentage']}% active")
                ->descriptionIcon('heroicon-m-cloud')
                ->color($this->getHealthColor($dnsProviders['health_percentage'])),

            Stat::make('Mail Servers', "{$mailServers['running']}/{$mailServers['total']}")
                ->description("{$mailServers['health_percentage']}% running")
                ->descriptionIcon('heroicon-m-envelope')
                ->color($this->getHealthColor($mailServers['health_percentage'])),

            Stat::make('Web Servers', "{$webServers['running']}/{$webServers['total']}")
                ->description("{$webServers['health_percentage']}% running")
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($this->getHealthColor($webServers['health_percentage'])),
        ];
    }

    protected function getHealthColor(int $percentage): string
    {
        if ($percentage >= 80) {
            return 'success';
        }
        if ($percentage >= 50) {
            return 'warning';
        }

        return 'danger';
    }

    protected function getSshHostsStatus(): array
    {
        if (! class_exists(\NetServa\Core\Models\SshHost::class)) {
            return ['total' => 0, 'online' => 0, 'offline' => 0, 'health_percentage' => 0];
        }

        $total = \NetServa\Core\Models\SshHost::count();
        $online = \NetServa\Core\Models\SshHost::where('status', 'online')->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
            'health_percentage' => $total > 0 ? (int) round(($online / $total) * 100) : 0,
        ];
    }

    protected function getDnsProvidersStatus(): array
    {
        if (! class_exists(\NetServa\Dns\Models\DnsProvider::class)) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'health_percentage' => 0];
        }

        $total = \NetServa\Dns\Models\DnsProvider::count();
        $active = \NetServa\Dns\Models\DnsProvider::where('is_active', true)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'health_percentage' => $total > 0 ? (int) round(($active / $total) * 100) : 0,
        ];
    }

    protected function getMailServersStatus(): array
    {
        if (! class_exists(\NetServa\Mail\Models\MailServer::class)) {
            return ['total' => 0, 'running' => 0, 'stopped' => 0, 'health_percentage' => 0];
        }

        $total = \NetServa\Mail\Models\MailServer::count();
        $running = \NetServa\Mail\Models\MailServer::where('status', 'running')->count();

        return [
            'total' => $total,
            'running' => $running,
            'stopped' => $total - $running,
            'health_percentage' => $total > 0 ? (int) round(($running / $total) * 100) : 0,
        ];
    }

    protected function getWebServersStatus(): array
    {
        if (! class_exists(\NetServa\Web\Models\WebServer::class)) {
            return ['total' => 0, 'running' => 0, 'stopped' => 0, 'health_percentage' => 0];
        }

        $total = \NetServa\Web\Models\WebServer::count();
        $running = \NetServa\Web\Models\WebServer::where('status', 'running')->count();

        return [
            'total' => $total,
            'running' => $running,
            'stopped' => $total - $running,
            'health_percentage' => $total > 0 ? (int) round(($running / $total) * 100) : 0,
        ];
    }
}
