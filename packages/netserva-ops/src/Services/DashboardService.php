<?php

namespace NetServa\Ops\Services;

use Illuminate\Support\Collection;
use NetServa\Ops\Models\AnalyticsDashboard;
use NetServa\Ops\Models\AnalyticsVisualization;

class DashboardService
{
    public function __construct(
        private VisualizationService $visualizationService
    ) {}

    /**
     * Create a new dashboard
     */
    public function create(array $config): AnalyticsDashboard
    {
        return AnalyticsDashboard::create([
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'widgets' => $config['widgets'] ?? [],
            'layout_columns' => $config['layout_columns'] ?? 12,
            'refresh_interval' => $config['refresh_interval'] ?? 300,
            'is_public' => $config['is_public'] ?? false,
            'is_active' => $config['is_active'] ?? true,
        ]);
    }

    /**
     * Load dashboard with all visualization data
     */
    public function load(int $dashboardId): array
    {
        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);
        $widgets = [];

        foreach ($dashboard->widgets as $widget) {
            $visualization = AnalyticsVisualization::find($widget['viz_id']);

            if ($visualization && $visualization->is_active) {
                $widgets[] = [
                    'id' => $widget['viz_id'],
                    'position' => [
                        'x' => $widget['x'],
                        'y' => $widget['y'],
                        'width' => $widget['width'],
                        'height' => $widget['height'],
                    ],
                    'visualization' => $this->visualizationService->render($visualization),
                ];
            }
        }

        return [
            'id' => $dashboard->id,
            'name' => $dashboard->name,
            'description' => $dashboard->description,
            'layout_columns' => $dashboard->layout_columns,
            'refresh_interval' => $dashboard->refresh_interval,
            'widgets' => $widgets,
            'is_public' => $dashboard->is_public,
        ];
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(int $dashboardId, int $visualizationId, array $position): bool
    {
        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);

        // Check if visualization exists
        if (! AnalyticsVisualization::find($visualizationId)) {
            return false;
        }

        $widgets = $dashboard->widgets ?? [];

        $widgets[] = [
            'viz_id' => $visualizationId,
            'x' => $position['x'],
            'y' => $position['y'],
            'width' => $position['width'],
            'height' => $position['height'],
        ];

        $dashboard->update(['widgets' => $widgets]);

        return true;
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(int $dashboardId, int $visualizationId): bool
    {
        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);
        $widgets = $dashboard->widgets ?? [];

        // Check if widget exists in dashboard
        $originalCount = count($widgets);
        $widgets = array_filter($widgets, fn ($widget) => $widget['viz_id'] !== $visualizationId);

        // If no widget was removed, return false
        if (count($widgets) === $originalCount) {
            return false;
        }

        $dashboard->update(['widgets' => array_values($widgets)]);

        return true;
    }

    /**
     * Update widget position
     */
    public function updateWidgetPosition(int $dashboardId, int $visualizationId, array $position): bool
    {
        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);
        $widgets = $dashboard->widgets ?? [];

        foreach ($widgets as &$widget) {
            if ($widget['viz_id'] === $visualizationId) {
                $widget['x'] = $position['x'];
                $widget['y'] = $position['y'];
                $widget['width'] = $position['width'];
                $widget['height'] = $position['height'];
                break;
            }
        }

        $dashboard->update(['widgets' => $widgets]);

        return true;
    }

    /**
     * Get all active dashboards
     */
    public function getActive(): Collection
    {
        return AnalyticsDashboard::where('is_active', true)->get();
    }

    /**
     * Get public dashboards
     */
    public function getPublic(): Collection
    {
        return AnalyticsDashboard::where('is_public', true)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Update a dashboard
     */
    public function update(int $dashboardId, array $data): AnalyticsDashboard
    {
        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);
        $dashboard->update($data);

        return $dashboard;
    }
}
