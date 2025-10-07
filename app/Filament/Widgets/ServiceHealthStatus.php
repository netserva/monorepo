<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use NetServa\Core\Models\SshHost;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Mail\Models\MailServer;
use NetServa\Web\Models\WebServer;

/**
 * Service Health Status Widget
 *
 * Displays real-time health status of critical infrastructure services
 */
class ServiceHealthStatus extends Widget
{
    protected string $view = 'filament.widgets.service-health-status';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        return [
            'ssh_hosts' => $this->getSshHostsStatus(),
            'dns_providers' => $this->getDnsProvidersStatus(),
            'mail_servers' => $this->getMailServersStatus(),
            'web_servers' => $this->getWebServersStatus(),
        ];
    }

    protected function getSshHostsStatus(): array
    {
        if (! class_exists(SshHost::class)) {
            return ['total' => 0, 'online' => 0, 'offline' => 0];
        }

        $total = SshHost::count();
        $online = SshHost::where('status', 'online')->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
            'health_percentage' => $total > 0 ? round(($online / $total) * 100) : 0,
        ];
    }

    protected function getDnsProvidersStatus(): array
    {
        if (! class_exists(DnsProvider::class)) {
            return ['total' => 0, 'active' => 0];
        }

        $total = DnsProvider::count();
        $active = DnsProvider::where('is_active', true)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'health_percentage' => $total > 0 ? round(($active / $total) * 100) : 0,
        ];
    }

    protected function getMailServersStatus(): array
    {
        if (! class_exists(MailServer::class)) {
            return ['total' => 0, 'running' => 0];
        }

        $total = MailServer::count();
        $running = MailServer::where('status', 'running')->count();

        return [
            'total' => $total,
            'running' => $running,
            'stopped' => $total - $running,
            'health_percentage' => $total > 0 ? round(($running / $total) * 100) : 0,
        ];
    }

    protected function getWebServersStatus(): array
    {
        if (! class_exists(WebServer::class)) {
            return ['total' => 0, 'running' => 0];
        }

        $total = WebServer::count();
        $running = WebServer::where('status', 'running')->count();

        return [
            'total' => $total,
            'running' => $running,
            'stopped' => $total - $running,
            'health_percentage' => $total > 0 ? round(($running / $total) * 100) : 0,
        ];
    }
}
