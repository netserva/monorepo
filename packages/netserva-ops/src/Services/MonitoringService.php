<?php

namespace NetServa\Ops\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NetServa\Ops\Models\AlertRule;
use NetServa\Ops\Models\Incident;
use NetServa\Ops\Models\MetricCollector;
use NetServa\Ops\Models\MonitoringCheck;

class MonitoringService
{
    /**
     * Create a new monitoring check
     */
    public function createCheck(array $data): MonitoringCheck
    {
        return MonitoringCheck::create($data);
    }

    /**
     * Perform a monitoring check
     */
    public function performCheck(MonitoringCheck $check): array
    {
        try {
            switch ($check->check_type) {
                case 'http':
                    return $this->performHttpCheck($check);
                case 'tcp':
                    return $this->performTcpCheck($check);
                case 'ssl':
                    return $this->checkSslExpiration($check);
                default:
                    return ['success' => false, 'error' => 'Unknown check type'];
            }
        } catch (\Exception $e) {
            Log::error("Monitoring check failed: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Perform HTTP check
     */
    protected function performHttpCheck(MonitoringCheck $check): array
    {
        try {
            $config = $check->check_config ?? [];
            $response = Http::timeout($config['timeout'] ?? 30)->get($config['url']);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
                'body' => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform TCP check
     */
    public function performTcpCheck(MonitoringCheck $check): array
    {
        $config = $check->check_config ?? [];
        $host = $config['host'] ?? ($config['url'] ? parse_url($config['url'], PHP_URL_HOST) : null);
        $port = $config['port'] ?? ($config['url'] ? parse_url($config['url'], PHP_URL_PORT) : null) ?? 80;
        $timeout = $config['timeout'] ?? 30;

        $startTime = microtime(true);
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($connection) {
            fclose($connection);

            return [
                'success' => true,
                'response_time' => $responseTime,
                'host' => $host,
                'port' => $port,
            ];
        }

        return [
            'success' => false,
            'error' => "Connection failed: $errstr ($errno)",
            'response_time' => $responseTime,
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * Check SSL certificate expiration
     */
    public function checkSslExpiration(MonitoringCheck $check): array
    {
        $config = $check->check_config ?? [];
        $url = parse_url($config['url'] ?? '');
        $host = $url['host'] ?? '';
        $port = $url['port'] ?? 443;

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $stream = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $stream) {
            return [
                'success' => false,
                'error' => "Failed to connect: $errstr",
            ];
        }

        $cert = stream_context_get_params($stream)['options']['ssl']['peer_certificate'];
        fclose($stream);

        $certData = openssl_x509_parse($cert);
        $expiryDate = Carbon::createFromTimestamp($certData['validTo_time_t']);
        $daysUntilExpiry = $expiryDate->diffInDays(Carbon::now());

        return [
            'success' => true,
            'expires_at' => $expiryDate->toISOString(),
            'days_until_expiry' => $daysUntilExpiry,
            'expired' => $expiryDate->isPast(),
        ];
    }

    /**
     * Collect metrics from a collector
     */
    public function collectMetrics(MetricCollector $collector): array
    {
        // Simulate metric collection based on collector type
        switch ($collector->type) {
            case 'system':
                return $this->collectSystemMetrics();
            case 'database':
                return $this->collectDatabaseMetrics($collector->connection ?? 'mysql', $collector->config ?? []);
            case 'http':
                return $this->collectHttpMetrics($collector);
            default:
                return [];
        }
    }

    /**
     * Collect system metrics
     */
    public function collectSystemMetrics(): array
    {
        // Simulate system metrics
        return [
            'cpu' => [
                'usage' => rand(10, 90),
            ],
            'memory' => [
                'used' => rand(1000, 8000),
                'total' => 8192,
            ],
            'disk' => [
                'used' => rand(20, 70),
                'total' => 100,
            ],
        ];
    }

    /**
     * Collect database metrics
     */
    public function collectDatabaseMetrics(string $connection, array $config): array
    {
        // Simulate database metrics
        return [
            'connection' => $connection,
            'queries_per_second' => rand(100, 1000),
            'connections' => rand(10, 100),
            'active_processes' => rand(1, 20),
            'slow_queries' => rand(0, 10),
            'uptime' => rand(3600, 86400),
        ];
    }

    /**
     * Collect HTTP metrics
     */
    protected function collectHttpMetrics(MetricCollector $collector): array
    {
        try {
            $response = Http::get($collector->url);

            return [
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
                'status_code' => $response->status(),
                'success' => $response->successful(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create an alert rule
     */
    public function createAlertRule(array $data): AlertRule
    {
        return AlertRule::create($data);
    }

    /**
     * Evaluate alert condition
     */
    public function evaluateAlertCondition(AlertRule $rule, float $value): bool
    {
        $operator = $rule->comparison_operator ?? $rule->operator ?? '>';
        $threshold = $rule->threshold_value ?? $rule->threshold ?? 0;

        switch ($operator) {
            case 'gt':
            case '>':
                return $value > $threshold;
            case 'gte':
            case '>=':
                return $value >= $threshold;
            case 'lt':
            case '<':
                return $value < $threshold;
            case 'lte':
            case '<=':
                return $value <= $threshold;
            case 'eq':
            case '=':
            case '==':
                return $value == $threshold;
            case 'ne':
            case '!=':
                return $value != $threshold;
            default:
                return false;
        }
    }

    /**
     * Create an incident
     */
    public function createIncident(AlertRule $rule, array $context): Incident
    {
        return Incident::create([
            'alert_rule_id' => $rule->id,
            'title' => $rule->name,
            'description' => $rule->message ?? 'Alert condition triggered',
            'severity' => $rule->severity ?? 'warning',
            'context' => $context,
            'status' => 'open',
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Aggregate metrics
     */
    public function aggregateMetrics(MetricCollector $collector, string $function, int $timeWindow): array
    {
        // Get data points from the collector
        $dataPoints = $collector->metricDataPoints;
        $values = $dataPoints->pluck('value')->toArray();

        if (empty($values)) {
            return [
                'value' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'count' => 0,
            ];
        }

        return [
            'value' => match ($function) {
                'avg' => array_sum($values) / count($values),
                'sum' => array_sum($values),
                'max' => max($values),
                'min' => min($values),
                default => array_sum($values) / count($values),
            },
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / count($values),
            'count' => count($values),
        ];
    }

    /**
     * Generate status page data
     */
    public function generateStatusPageData(): array
    {
        $checks = MonitoringCheck::all();

        $services = $checks->map(function ($check) {
            return [
                'name' => $check->name,
                'status' => $check->status,
            ];
        })->toArray();

        $upCount = $checks->where('status', 'up')->count();
        $totalCount = $checks->count();
        $uptimePercentage = $totalCount > 0 ? ($upCount / $totalCount) * 100 : 100;

        $overallStatus = match (true) {
            $upCount === $totalCount => 'operational',
            $upCount === 0 => 'down',
            default => 'partial'
        };

        return [
            'overall_status' => $overallStatus,
            'services' => $services,
            'uptime_percentage' => $uptimePercentage,
        ];
    }

    /**
     * Validate check configuration
     */
    public function validateCheckConfig(array $config): array
    {
        $errors = [];

        if (empty($config['name'])) {
            $errors[] = 'Name is required';
        }

        if (empty($config['type'])) {
            $errors[] = 'Type is required';
        } elseif (! in_array($config['type'], ['http', 'tcp', 'ssl', 'ping', 'dns'])) {
            $errors[] = 'Invalid check type';
        }

        if (isset($config['type'])) {
            switch ($config['type']) {
                case 'http':
                    if (empty($config['url'])) {
                        $errors[] = 'URL is required for HTTP checks';
                    }
                    break;
                case 'tcp':
                    if (empty($config['host'])) {
                        $errors[] = 'Host is required for TCP checks';
                    }
                    if (empty($config['port'])) {
                        $errors[] = 'Port is required for TCP checks';
                    }
                    break;
                case 'dns':
                    if (empty($config['domain'])) {
                        $errors[] = 'Domain is required for DNS checks';
                    }
                    if (empty($config['record_type'])) {
                        $errors[] = 'Record type is required for DNS checks';
                    }
                    if (empty($config['expected_value'])) {
                        $errors[] = 'Expected value is required for DNS checks';
                    }
                    break;
                case 'ssl':
                    if (empty($config['domain'])) {
                        $errors[] = 'Domain is required for SSL checks';
                    }
                    break;
                case 'ping':
                    if (empty($config['host'])) {
                        $errors[] = 'Host is required for ping checks';
                    }
                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
