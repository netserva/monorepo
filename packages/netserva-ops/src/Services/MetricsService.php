<?php

namespace NetServa\Ops\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use NetServa\Ops\Models\AnalyticsDataSource;
use NetServa\Ops\Models\AnalyticsMetric;

class MetricsService
{
    /**
     * Create a new metric
     */
    public function create(array $config): AnalyticsMetric
    {
        return AnalyticsMetric::create([
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'analytics_data_source_id' => $config['analytics_data_source_id'],
            'query' => $config['query'],
            'frequency' => $config['frequency'] ?? 'hourly',
            'unit' => $config['unit'] ?? 'count',
            'type' => $config['type'] ?? 'number',
            'threshold_warning' => $config['threshold_warning'] ?? null,
            'threshold_critical' => $config['threshold_critical'] ?? null,
            'is_active' => $config['is_active'] ?? true,
        ]);
    }

    /**
     * Update a metric
     */
    public function update(int $metricId, array $data): AnalyticsMetric
    {
        $metric = AnalyticsMetric::findOrFail($metricId);
        $metric->update($data);

        return $metric;
    }

    /**
     * Delete a metric
     */
    public function delete(int $metricId): bool
    {
        return AnalyticsMetric::findOrFail($metricId)->delete();
    }

    /**
     * Collect data for a specific metric
     */
    public function collect(AnalyticsMetric $metric): bool
    {
        try {
            $dataSource = $metric->dataSource;
            $value = $this->executeQuery($dataSource, $metric->query);

            $metric->update([
                'value' => $value,
                'collected_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw
            return false;
        }
    }

    /**
     * Collect data for all active metrics
     */
    public function collectAll(): array
    {
        $metrics = AnalyticsMetric::where('is_active', true)->get();
        $results = ['successful' => 0, 'failed' => 0];

        foreach ($metrics as $metric) {
            if ($this->collect($metric)) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get all active metrics
     */
    public function getActive(): Collection
    {
        return AnalyticsMetric::where('is_active', true)->get();
    }

    /**
     * Get metrics by frequency
     */
    public function getByFrequency(string $frequency): Collection
    {
        return AnalyticsMetric::where('frequency', $frequency)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Execute query on data source
     */
    private function executeQuery(AnalyticsDataSource $dataSource, string $query): float
    {
        return match ($dataSource->type) {
            'database' => $this->executeDatabaseQuery($dataSource, $query),
            'api' => $this->executeApiQuery($dataSource, $query),
            'csv' => $this->executeCsvQuery($dataSource, $query),
            default => throw new Exception("Unsupported data source type: {$dataSource->type}")
        };
    }

    /**
     * Execute database query
     */
    private function executeDatabaseQuery(AnalyticsDataSource $dataSource, string $query): float
    {
        $config = $dataSource->connection;

        // Create temporary connection
        config([
            'database.connections.analytics_temp' => [
                'driver' => 'mysql',
                'host' => $config['host'],
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'],
            ],
        ]);

        $result = DB::connection('analytics_temp')->select($query);

        return (float) array_values((array) $result[0])[0];
    }

    /**
     * Execute API query
     */
    private function executeApiQuery(AnalyticsDataSource $dataSource, string $endpoint): float
    {
        $config = $dataSource->connection;
        $response = Http::get($config['host'].$endpoint);

        if (! $response->successful()) {
            throw new Exception("API request failed: {$response->status()}");
        }

        $data = $response->json();

        // Assume the API returns a numeric value or object with 'value' key
        return (float) (is_array($data) ? $data['value'] ?? $data[0] : $data);
    }

    /**
     * Execute CSV query (basic filtering)
     */
    private function executeCsvQuery(AnalyticsDataSource $dataSource, string $query): float
    {
        $config = $dataSource->connection;
        $filePath = $config['file_path'];

        if (! file_exists($filePath)) {
            throw new Exception("CSV file not found: {$filePath}");
        }

        $csvData = array_map('str_getcsv', file($filePath));
        $headers = array_shift($csvData);

        // Simple count for now - could be enhanced with actual CSV querying
        return (float) count($csvData);
    }

    /**
     * Check metrics that need collection based on frequency
     */
    public function getMetricsNeedingCollection(): Collection
    {
        return AnalyticsMetric::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('collected_at')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('frequency', 'hourly')
                            ->where('collected_at', '<', now()->subHour());
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('frequency', 'daily')
                            ->where('collected_at', '<', now()->subDay());
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('frequency', 'weekly')
                            ->where('collected_at', '<', now()->subWeek());
                    });
            })
            ->get();
    }
}
