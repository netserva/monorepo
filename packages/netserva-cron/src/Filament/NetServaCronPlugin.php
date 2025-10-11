<?php

namespace NetServa\Cron\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Cron\Filament\Resources\AutomationJobResource;
use NetServa\Cron\Filament\Resources\AutomationTaskResource;

/**
 * NetServa Cron/Automation Plugin
 *
 * Provides automated task scheduling and job management for NetServa infrastructure.
 *
 * Features:
 * - Cron job scheduling and management
 * - Automation task workflows
 * - Job execution tracking
 * - Schedule visualization
 *
 * @package NetServa\Cron\Filament
 */
class NetServaCronPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-cron';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
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
        // Planned groups: Automation
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
                'automation_jobs' => true,
                'automation_tasks' => true,
                'schedule_visualization' => true,
            ],
            'settings' => [
                'default_timezone' => 'UTC',
                'max_concurrent_jobs' => 5,
            ],
        ];
    }
}
