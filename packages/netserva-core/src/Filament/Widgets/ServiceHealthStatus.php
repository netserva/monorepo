<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Service Health Status Widget
 *
 * Displays real-time health status of critical infrastructure services
 */
class ServiceHealthStatus extends Widget
{
    protected string $view = 'netserva-core::widgets.service-health-status';

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
        if (! class_exists(\NetServa\Core\Models\SshHost::class)) {
            return ['total' => 0, 'online' => 0, 'offline' => 0, 'health_percentage' => 0];
        }

        $total = \NetServa\Core\Models\SshHost::count();
        $online = \NetServa\Core\Models\SshHost::where('status', 'online')->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
            'health_percentage' => $total > 0 ? round(($online / $total) * 100) : 0,
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
            'health_percentage' => $total > 0 ? round(($active / $total) * 100) : 0,
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
            'health_percentage' => $total > 0 ? round(($running / $total) * 100) : 0,
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
            'health_percentage' => $total > 0 ? round(($running / $total) * 100) : 0,
        ];
    }
}
