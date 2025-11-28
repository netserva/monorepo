<?php

namespace NetServa\Ops\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Ops\Filament\Resources\AlertRuleResource;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource;
use NetServa\Ops\Filament\Resources\AutomationJobResource;
use NetServa\Ops\Filament\Resources\AutomationTaskResource;
use NetServa\Ops\Filament\Resources\BackupJobResource;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource;
use NetServa\Ops\Filament\Resources\IncidentResource;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource;
use NetServa\Ops\Filament\Resources\StatusPageResource;

/**
 * NetServa Ops Plugin
 *
 * Provides comprehensive operations management for NetServa infrastructure.
 * Handles monitoring, alerting, backups, incidents, and analytics.
 *
 * Features:
 * - Backup management (jobs, repositories, snapshots)
 * - Infrastructure monitoring and health checks
 * - Alert rules and notifications
 * - Incident tracking and status pages
 * - Analytics metrics and dashboards
 * - Data visualization and reporting
 * - Automation tasks and job execution (merged from cron package)
 */
class NetServaOpsPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-ops';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            // Monitoring
            MonitoringCheckResource::class,
            AlertRuleResource::class,
            IncidentResource::class,
            StatusPageResource::class,
            // Backup
            BackupJobResource::class,
            BackupRepositoryResource::class,
            BackupSnapshotResource::class,
            // Analytics
            AnalyticsDashboardResource::class,
            AnalyticsMetricResource::class,
            AnalyticsDataSourceResource::class,
            AnalyticsVisualizationResource::class,
            AnalyticsAlertResource::class,
            // Automation (merged from cron)
            AutomationJobResource::class,
            AutomationTaskResource::class,
        ]);
    }

    protected function registerPages(Panel $panel): void
    {
        // No custom pages currently
    }

    protected function registerWidgets(Panel $panel): void
    {
        // No widgets currently
    }

    protected function registerNavigationItems(Panel $panel): void
    {
        // TODO: Navigation groups should be defined in Resource classes as protected static properties
        // This is the Filament 4.x pattern. For now, resources will use default navigation.
        //
        // Planned groups:
        // - Backups: BackupJobResource, BackupRepositoryResource, BackupSnapshotResource
        // - Monitoring: MonitoringCheckResource, AlertRuleResource, IncidentResource, StatusPageResource
        // - Analytics: AnalyticsMetricResource, AnalyticsDataSourceResource, AnalyticsDashboardResource,
        //             AnalyticsVisualizationResource, AnalyticsAlertResource
    }

    public function getVersion(): string
    {
        return '3.0.0';
    }

    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [
                'backup_management' => true,
                'monitoring_checks' => true,
                'alert_rules' => true,
                'incident_tracking' => true,
                'analytics_metrics' => true,
                'status_pages' => true,
            ],
            'settings' => [
                'backup_retention_days' => 30,
                'monitoring_interval' => 300, // 5 minutes
                'alert_cooldown' => 3600, // 1 hour
                'analytics_retention_days' => 90,
            ],
        ];
    }
}
