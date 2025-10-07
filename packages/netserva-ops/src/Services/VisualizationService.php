<?php

namespace NetServa\Ops\Services;

use Exception;
use Illuminate\Support\Collection;
use NetServa\Ops\Models\AnalyticsMetric;
use NetServa\Ops\Models\AnalyticsVisualization;

class VisualizationService
{
    /**
     * Create a new visualization
     */
    public function create(array $config): AnalyticsVisualization
    {
        return AnalyticsVisualization::create([
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'type' => $config['type'],
            'metric_ids' => $config['metric_ids'],
            'config' => $config['config'] ?? [],
            'refresh_interval' => $config['refresh_interval'] ?? 300,
            'is_active' => $config['is_active'] ?? true,
        ]);
    }

    /**
     * Render visualization data
     */
    public function render(AnalyticsVisualization $visualization): array
    {
        $metrics = $this->getMetrics($visualization->metric_ids);

        return match ($visualization->type) {
            'line' => $this->renderLineChart($metrics, $visualization->config),
            'bar' => $this->renderBarChart($metrics, $visualization->config),
            'pie' => $this->renderPieChart($metrics, $visualization->config),
            'table' => $this->renderTable($metrics, $visualization->config),
            'metric' => $this->renderMetricCard($metrics, $visualization->config),
            default => throw new Exception("Unsupported visualization type: {$visualization->type}")
        };
    }

    /**
     * Get metrics by IDs
     */
    private function getMetrics(array $metricIds): Collection
    {
        return AnalyticsMetric::whereIn('id', $metricIds)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Render line chart
     */
    private function renderLineChart(Collection $metrics, array $config): array
    {
        $data = [];
        $labels = [];

        foreach ($metrics as $metric) {
            $data[] = [
                'label' => $metric->name,
                'value' => $metric->value,
                'unit' => $metric->unit,
            ];
            $labels[] = $metric->name;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $config['title'] ?? 'Metrics',
                    'data' => array_column($data, 'value'),
                    'backgroundColor' => $config['color'] ?? '#007bff',
                ]],
            ],
        ];
    }

    /**
     * Render bar chart
     */
    private function renderBarChart(Collection $metrics, array $config): array
    {
        $data = [];
        $labels = [];

        foreach ($metrics as $metric) {
            $data[] = $metric->value;
            $labels[] = $metric->name;
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $config['title'] ?? 'Metrics',
                    'data' => $data,
                    'backgroundColor' => $config['color'] ?? '#28a745',
                ]],
            ],
        ];
    }

    /**
     * Render pie chart
     */
    private function renderPieChart(Collection $metrics, array $config): array
    {
        $data = [];
        $labels = [];

        foreach ($metrics as $metric) {
            $data[] = $metric->value;
            $labels[] = $metric->name;
        }

        return [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8',
                    ],
                ]],
            ],
        ];
    }

    /**
     * Render table
     */
    private function renderTable(Collection $metrics, array $config): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            $rows[] = [
                'name' => $metric->name,
                'value' => $metric->value,
                'unit' => $metric->unit,
                'collected_at' => $metric->collected_at?->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'type' => 'table',
            'data' => [
                'columns' => ['Name', 'Value', 'Unit', 'Collected At'],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Render metric card
     */
    private function renderMetricCard(Collection $metrics, array $config): array
    {
        $metric = $metrics->first();

        if (! $metric) {
            throw new Exception('No metric available for metric card');
        }

        return [
            'type' => 'metric',
            'data' => [
                'title' => $config['title'] ?? $metric->name,
                'value' => $metric->value,
                'unit' => $metric->unit,
                'collected_at' => $metric->collected_at?->format('Y-m-d H:i:s'),
                'status' => $this->getMetricStatus($metric),
            ],
        ];
    }

    /**
     * Get metric status based on thresholds
     */
    private function getMetricStatus(AnalyticsMetric $metric): string
    {
        if ($metric->threshold_critical && $metric->value >= $metric->threshold_critical) {
            return 'critical';
        }

        if ($metric->threshold_warning && $metric->value >= $metric->threshold_warning) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Get all active visualizations
     */
    public function getActive(): Collection
    {
        return AnalyticsVisualization::where('is_active', true)->get();
    }

    /**
     * Get visualizations by type
     */
    public function getByType(string $type): Collection
    {
        return AnalyticsVisualization::where('type', $type)
            ->where('is_active', true)
            ->get();
    }
}
