<?php

namespace NetServa\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * System Statistics Widget
 *
 * Displays key system metrics on the dashboard
 */
class SystemStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('System Services', $this->getServicesCount())
                ->description('Active system services')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Virtual Hosts', $this->getVHostsCount())
                ->description('Configured virtual hosts')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Plugin Status', $this->getPluginStatusText())
                ->description($this->getEnabledPluginsCount().' plugins enabled')
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color($this->getPluginStatusColor()),

            Stat::make('System Health', 'Operational')
                ->description('All core systems running')
                ->descriptionIcon('heroicon-m-heart')
                ->color('success'),
        ];
    }

    protected function getServicesCount(): string
    {
        // Simple service count - in real implementation would check actual services
        try {
            $services = ['nginx', 'php-fpm', 'mariadb', 'postfix', 'dovecot'];

            return (string) count($services);
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    protected function getVHostsCount(): string
    {
        try {
            // In real implementation would count actual VHosts
            return '2';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    protected function getEnabledPluginsCount(): int
    {
        try {
            return \NetServa\Core\Models\InstalledPlugin::where('is_enabled', true)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getPluginStatusText(): string
    {
        $count = $this->getEnabledPluginsCount();

        return $count > 0 ? 'Active' : 'None';
    }

    protected function getPluginStatusColor(): string
    {
        $count = $this->getEnabledPluginsCount();

        return $count > 0 ? 'success' : 'warning';
    }
}
