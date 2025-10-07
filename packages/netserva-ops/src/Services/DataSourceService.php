<?php

namespace NetServa\Ops\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use NetServa\Ops\Models\AnalyticsDataSource;

class DataSourceService
{
    /**
     * Create a new analytics data source
     */
    public function create(array $config): AnalyticsDataSource
    {
        // Map common type aliases to valid enum values
        $typeMapping = [
            'log_file' => 'file',
            'database' => 'database',
            'api' => 'api',
            'webhook' => 'webhook',
            'stream' => 'stream',
        ];

        $sourceType = $config['type'] ?? $config['source_type'] ?? 'file';
        $sourceType = $typeMapping[$sourceType] ?? $sourceType;

        return AnalyticsDataSource::create([
            'name' => $config['name'],
            'source_type' => $sourceType,
            'connection_type' => $config['connection_type'] ?? 'json',
            'connection_config' => $config['connection_config'] ?? $config['connection_string'] ?? [],
            'schema_definition' => $config['schema'] ?? $config['parser_config'] ?? [],
            'is_active' => $config['is_active'] ?? $config['enabled'] ?? true,
            'refresh_interval' => $config['refresh_interval'] ?? 3600, // 1 hour default
            'last_refreshed_at' => null,
        ]);
    }

    /**
     * Test connection to a data source
     */
    public function testConnection(AnalyticsDataSource $dataSource): array
    {
        try {
            switch ($dataSource->source_type) {
                case 'database':
                    return $this->testDatabaseConnection($dataSource);
                case 'api':
                    return $this->testApiConnection($dataSource);
                case 'file':
                    return $this->testFileConnection($dataSource);
                default:
                    throw new \InvalidArgumentException("Unsupported data source type: {$dataSource->source_type}");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tested_at' => now(),
            ];
        }
    }

    /**
     * Extract data from a data source with optional filters
     */
    public function extractData(AnalyticsDataSource $dataSource, array $filters = []): Collection
    {
        switch ($dataSource->source_type) {
            case 'database':
                return $this->extractDatabaseData($dataSource, $filters);
            case 'api':
                return $this->extractApiData($dataSource, $filters);
            case 'file':
                return $this->extractFileData($dataSource, $filters);
            case 'stream':
                return $this->extractStreamData($dataSource, $filters);
            default:
                throw new \InvalidArgumentException("Unsupported data source type: {$dataSource->source_type}");
        }
    }

    /**
     * Sync data from external source to local cache
     */
    public function syncData(AnalyticsDataSource $dataSource): array
    {
        $startTime = microtime(true);

        try {
            $data = $this->extractData($dataSource);

            // Store in cache or temporary table for faster access
            $this->storeDataInCache($dataSource, $data);

            $dataSource->update([
                'last_refreshed_at' => now(),
                'health_status' => 'healthy',
                'last_error_message' => null,
            ]);

            return [
                'success' => true,
                'records_synced' => $data->count(),
                'sync_time' => round(microtime(true) - $startTime, 2),
                'synced_at' => now(),
            ];
        } catch (\Exception $e) {
            $dataSource->update([
                'health_status' => 'error',
                'last_error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'sync_time' => round(microtime(true) - $startTime, 2),
            ];
        }
    }

    /**
     * Get data source schema information
     */
    public function getSchema(AnalyticsDataSource $dataSource): array
    {
        if (! empty($dataSource->schema_definition)) {
            return $dataSource->schema_definition;
        }

        // Auto-detect schema based on data source type
        switch ($dataSource->source_type) {
            case 'database':
                return $this->detectDatabaseSchema($dataSource);
            case 'api':
                return $this->detectApiSchema($dataSource);
            case 'file':
                return $this->detectFileSchema($dataSource);
            default:
                return [];
        }
    }

    /**
     * Test database connection
     */
    protected function testDatabaseConnection(AnalyticsDataSource $dataSource): array
    {
        $config = $dataSource->connection_config;

        $connection = DB::connection()->getPdo();

        // Test a simple query
        $result = DB::select('SELECT 1 as test');

        return [
            'success' => ! empty($result),
            'message' => 'Database connection successful',
            'tested_at' => now(),
        ];
    }

    /**
     * Test API connection
     */
    protected function testApiConnection(AnalyticsDataSource $dataSource): array
    {
        $config = $dataSource->connection_config;

        $response = Http::withHeaders($config['headers'] ?? [])
            ->timeout(30)
            ->get($config['url']);

        return [
            'success' => $response->successful(),
            'status_code' => $response->status(),
            'message' => $response->successful() ? 'API connection successful' : 'API connection failed',
            'tested_at' => now(),
        ];
    }

    /**
     * Test file connection
     */
    protected function testFileConnection(AnalyticsDataSource $dataSource): array
    {
        $config = $dataSource->connection_config;

        $exists = file_exists($config['path']);
        $readable = $exists && is_readable($config['path']);

        return [
            'success' => $readable,
            'message' => $readable ? 'File accessible' : 'File not accessible',
            'file_size' => $exists ? filesize($config['path']) : 0,
            'tested_at' => now(),
        ];
    }

    /**
     * Extract data from database source
     */
    protected function extractDatabaseData(AnalyticsDataSource $dataSource, array $filters = []): Collection
    {
        $config = $dataSource->connection_config;
        $query = $config['query'];

        // Apply filters to query
        foreach ($filters as $key => $value) {
            $query = str_replace(":{$key}", $value, $query);
        }

        $results = DB::select($query);

        return collect($results);
    }

    /**
     * Extract data from API source
     */
    protected function extractApiData(AnalyticsDataSource $dataSource, array $filters = []): Collection
    {
        $config = $dataSource->connection_config;

        if (empty($config['url'])) {
            throw new \Exception('API URL not configured for data source');
        }

        $url = $config['url'];
        $params = array_merge($config['params'] ?? [], $filters);

        $response = Http::withHeaders($config['headers'] ?? [])
            ->get($url, $params);

        if (! $response->successful()) {
            throw new \Exception("API request failed: {$response->status()}");
        }

        $data = $response->json();

        // Extract data from nested response if path is specified
        if (isset($config['data_path'])) {
            $data = data_get($data, $config['data_path']);
        }

        return collect($data);
    }

    /**
     * Extract data from file source
     */
    protected function extractFileData(AnalyticsDataSource $dataSource, array $filters = []): Collection
    {
        $config = $dataSource->connection_config;
        $filePath = $config['path'];

        if (! file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch (strtolower($extension)) {
            case 'json':
                $data = json_decode(file_get_contents($filePath), true);
                break;
            case 'csv':
                $data = $this->parseCsvFile($filePath);
                break;
            default:
                throw new \Exception("Unsupported file format: {$extension}");
        }

        $collection = collect($data);

        // Apply filters
        foreach ($filters as $key => $value) {
            $collection = $collection->where($key, $value);
        }

        return $collection;
    }

    /**
     * Extract data from stream source (WebSocket, SSE, etc.)
     */
    protected function extractStreamData(AnalyticsDataSource $dataSource, array $filters = []): Collection
    {
        // For testing purposes, return mock stream data
        // In real implementation, this would connect to WebSocket/SSE endpoint
        $mockData = [
            ['timestamp' => now()->timestamp, 'value' => rand(100, 200)],
            ['timestamp' => now()->addMinute()->timestamp, 'value' => rand(100, 200)],
            ['timestamp' => now()->addMinutes(2)->timestamp, 'value' => rand(100, 200)],
        ];

        return collect($mockData);
    }

    /**
     * Parse CSV file into array
     */
    protected function parseCsvFile(string $filePath): array
    {
        $data = [];
        $headers = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $lineNumber = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($lineNumber === 0) {
                    $headers = $row;
                } else {
                    $data[] = array_combine($headers, $row);
                }
                $lineNumber++;
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Store data in cache for faster access
     */
    protected function storeDataInCache(AnalyticsDataSource $dataSource, Collection $data): void
    {
        $cacheKey = "analytics_data_source_{$dataSource->id}";
        cache()->put($cacheKey, $data->toArray(), $dataSource->refresh_interval);
    }

    /**
     * Detect database schema
     */
    protected function detectDatabaseSchema(AnalyticsDataSource $dataSource): array
    {
        // This would analyze database tables/columns
        // For now, return a simple schema
        return [
            'type' => 'database',
            'fields' => [],
            'detected_at' => now(),
        ];
    }

    /**
     * Detect API schema
     */
    protected function detectApiSchema(AnalyticsDataSource $dataSource): array
    {
        // This would make a sample API call and analyze response structure
        return [
            'type' => 'api',
            'fields' => [],
            'detected_at' => now(),
        ];
    }

    /**
     * Detect file schema
     */
    protected function detectFileSchema(AnalyticsDataSource $dataSource): array
    {
        // This would analyze file structure
        return [
            'type' => 'file',
            'fields' => [],
            'detected_at' => now(),
        ];
    }
}
