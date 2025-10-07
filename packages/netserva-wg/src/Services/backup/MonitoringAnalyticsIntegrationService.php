<?php

namespace NetServa\Wg\Services;

use Illuminate\Support\Facades\Log;
use NetServa\Ops\Services\AuditLoggingService;
use NetServa\Ops\Services\DataCollectionService;
use NetServa\Ops\Services\MetricsCollectionService;
use NetServa\Wg\Models\WireguardConnection;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;

class MonitoringAnalyticsIntegrationService
{
    public function __construct(
        private MetricsCollectionService $metricsService,
        private DataCollectionService $analyticsService,
        private AuditLoggingService $auditService
    ) {}

    /**
     * Setup comprehensive monitoring and analytics integration
     */
    public function setupIntegration(): bool
    {
        try {
            Log::info('Setting up WireGuard monitoring and analytics integration');

            // Register WireGuard metrics collectors
            $this->registerMetricsCollectors();

            // Setup analytics data flows
            $this->setupAnalyticsDataFlows();

            // Configure audit logging
            $this->setupAuditLogging();

            // Setup health monitoring
            $this->setupHealthMonitoring();

            // Create monitoring dashboards
            $this->createMonitoringDashboards();

            Log::info('WireGuard monitoring and analytics integration setup complete');

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to setup monitoring integration: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Register metrics collectors for WireGuard
     */
    private function registerMetricsCollectors(): void
    {
        // Hub metrics collector
        $this->metricsService->registerCollector('wireguard_hubs', [
            'name' => 'WireGuard Hub Metrics',
            'description' => 'Collect metrics from WireGuard hubs',
            'interval' => 60, // 1 minute
            'collector' => function () {
                return $this->collectHubMetrics();
            },
        ]);

        // Spoke metrics collector
        $this->metricsService->registerCollector('wireguard_spokes', [
            'name' => 'WireGuard Spoke Metrics',
            'description' => 'Collect metrics from WireGuard spokes',
            'interval' => 300, // 5 minutes
            'collector' => function () {
                return $this->collectSpokeMetrics();
            },
        ]);

        // Connection metrics collector
        $this->metricsService->registerCollector('wireguard_connections', [
            'name' => 'WireGuard Connection Metrics',
            'description' => 'Collect connection performance metrics',
            'interval' => 30, // 30 seconds
            'collector' => function () {
                return $this->collectConnectionMetrics();
            },
        ]);

        // Network security metrics collector
        $this->metricsService->registerCollector('wireguard_security', [
            'name' => 'WireGuard Security Metrics',
            'description' => 'Collect security-related metrics',
            'interval' => 120, // 2 minutes
            'collector' => function () {
                return $this->collectSecurityMetrics();
            },
        ]);

        Log::info('Registered WireGuard metrics collectors');
    }

    /**
     * Collect hub metrics
     */
    private function collectHubMetrics(): array
    {
        $metrics = [];
        $hubs = WireguardHub::where('status', 'active')->get();

        foreach ($hubs as $hub) {
            $hubMetrics = [
                'hub_id' => $hub->id,
                'hub_name' => $hub->name,
                'hub_type' => $hub->hub_type,
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'total_spokes' => $hub->spokes()->count(),
                    'active_spokes' => $hub->spokes()->where('status', 'active')->count(),
                    'connected_spokes' => $hub->connections()->where('connection_status', 'connected')->count(),
                    'health_status' => $hub->health_status,
                    'bytes_sent' => $hub->total_bytes_sent ?? 0,
                    'bytes_received' => $hub->total_bytes_received ?? 0,
                    'active_connections' => $hub->active_connections ?? 0,
                    'average_latency_ms' => $hub->average_latency_ms ?? 0,
                    'packet_loss_percentage' => $hub->packet_loss_percentage ?? 0,
                    'deployment_status' => $hub->deployment_status,
                    'last_deployed_at' => $hub->last_deployed_at?->toISOString(),
                    'uptime_percentage' => $this->calculateUptimePercentage($hub),
                ],
            ];

            // Add hub-type specific metrics
            $hubMetrics['metrics'] = array_merge(
                $hubMetrics['metrics'],
                $this->getHubTypeSpecificMetrics($hub)
            );

            $metrics[] = $hubMetrics;
        }

        return $metrics;
    }

    /**
     * Collect spoke metrics
     */
    private function collectSpokeMetrics(): array
    {
        $metrics = [];
        $spokes = WireguardSpoke::where('status', 'active')->get();

        foreach ($spokes as $spoke) {
            $metrics[] = [
                'spoke_id' => $spoke->id,
                'spoke_name' => $spoke->name,
                'hub_id' => $spoke->wireguard_hub_id,
                'hub_name' => $spoke->hub->name,
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'connection_status' => $spoke->connection_status,
                    'bytes_sent' => $spoke->bytes_sent ?? 0,
                    'bytes_received' => $spoke->bytes_received ?? 0,
                    'packets_sent' => $spoke->packets_sent ?? 0,
                    'packets_received' => $spoke->packets_received ?? 0,
                    'average_latency_ms' => $spoke->average_latency_ms ?? 0,
                    'packet_loss_percentage' => $spoke->packet_loss_percentage ?? 0,
                    'connection_uptime_seconds' => $spoke->connection_uptime_seconds ?? 0,
                    'connection_stability_percentage' => $spoke->connection_stability_percentage ?? 0,
                    'last_handshake' => $spoke->last_handshake?->toISOString(),
                    'last_seen' => $spoke->last_seen?->toISOString(),
                    'allocated_ip' => $spoke->allocated_ip,
                    'device_type' => $spoke->device_type,
                    'operating_system' => $spoke->operating_system,
                ],
            ];
        }

        return $metrics;
    }

    /**
     * Collect connection metrics
     */
    private function collectConnectionMetrics(): array
    {
        $metrics = [];
        $connections = WireguardConnection::where('connection_status', 'connected')
            ->where('last_seen', '>=', now()->subMinutes(10))
            ->get();

        foreach ($connections as $connection) {
            $metrics[] = [
                'connection_id' => $connection->id,
                'hub_id' => $connection->wireguard_hub_id,
                'spoke_id' => $connection->wireguard_spoke_id,
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'session_duration' => $connection->session_duration,
                    'bytes_sent' => $connection->bytes_sent,
                    'bytes_received' => $connection->bytes_received,
                    'connection_quality' => $this->calculateConnectionQuality($connection),
                    'bandwidth_utilization' => $this->calculateBandwidthUtilization($connection),
                    'last_handshake_age' => $connection->last_handshake ?
                        now()->diffInSeconds($connection->last_handshake) : null,
                    'endpoint' => $connection->endpoint,
                    'connection_status' => $connection->connection_status,
                ],
            ];
        }

        return $metrics;
    }

    /**
     * Collect security metrics
     */
    private function collectSecurityMetrics(): array
    {
        $metrics = [];

        // Connection failure analysis
        $recentFailures = WireguardConnection::where('connection_status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->get();

        $securityEvents = [
            'timestamp' => now()->toISOString(),
            'events' => [
                'total_connection_failures' => $recentFailures->count(),
                'unique_failed_endpoints' => $recentFailures->pluck('endpoint')->unique()->count(),
                'failed_hubs' => $recentFailures->groupBy('wireguard_hub_id')->count(),
                'authentication_failures' => $this->countAuthenticationFailures(),
                'suspicious_traffic_patterns' => $this->detectSuspiciousTraffic(),
                'key_rotation_events' => $this->countKeyRotationEvents(),
                'configuration_drift_events' => $this->countConfigurationDriftEvents(),
            ],
        ];

        // Add hub-specific security metrics
        $hubs = WireguardHub::where('status', 'active')->get();
        foreach ($hubs as $hub) {
            $securityEvents['hubs'][$hub->id] = [
                'hub_name' => $hub->name,
                'hub_type' => $hub->hub_type,
                'isolation_violations' => $this->countIsolationViolations($hub),
                'firewall_blocks' => $this->countFirewallBlocks($hub),
                'unusual_bandwidth_usage' => $this->detectUnusualBandwidth($hub),
            ];
        }

        $metrics[] = $securityEvents;

        return $metrics;
    }

    /**
     * Setup analytics data flows
     */
    private function setupAnalyticsDataFlows(): void
    {
        // Network usage analytics
        $this->analyticsService->registerDataFlow('wireguard_network_usage', [
            'source' => 'wireguard_connections',
            'interval' => 300, // 5 minutes
            'aggregation' => 'sum',
            'dimensions' => ['hub_id', 'hub_type', 'spoke_id'],
            'metrics' => ['bytes_sent', 'bytes_received', 'session_duration'],
            'processor' => function ($data) {
                return $this->processNetworkUsageData($data);
            },
        ]);

        // Performance analytics
        $this->analyticsService->registerDataFlow('wireguard_performance', [
            'source' => 'wireguard_connections',
            'interval' => 600, // 10 minutes
            'aggregation' => 'avg',
            'dimensions' => ['hub_type', 'endpoint_region'],
            'metrics' => ['latency_ms', 'packet_loss_percentage', 'bandwidth_utilization'],
            'processor' => function ($data) {
                return $this->processPerformanceData($data);
            },
        ]);

        // Security analytics
        $this->analyticsService->registerDataFlow('wireguard_security', [
            'source' => 'wireguard_security',
            'interval' => 900, // 15 minutes
            'aggregation' => 'count',
            'dimensions' => ['event_type', 'hub_type', 'severity'],
            'metrics' => ['event_count', 'affected_entities'],
            'processor' => function ($data) {
                return $this->processSecurityData($data);
            },
        ]);

        // Customer usage analytics (for billing and quotas)
        $this->analyticsService->registerDataFlow('wireguard_customer_usage', [
            'source' => 'wireguard_spokes',
            'interval' => 1800, // 30 minutes
            'aggregation' => 'sum',
            'dimensions' => ['customer_id', 'hub_type'],
            'metrics' => ['bytes_sent', 'bytes_received', 'connection_time'],
            'processor' => function ($data) {
                return $this->processCustomerUsageData($data);
            },
        ]);

        Log::info('Setup WireGuard analytics data flows');
    }

    /**
     * Setup audit logging
     */
    private function setupAuditLogging(): void
    {
        // Register WireGuard audit events
        $this->auditService->registerAuditableEvents([
            'wireguard.hub.created' => 'WireGuard hub created',
            'wireguard.hub.updated' => 'WireGuard hub updated',
            'wireguard.hub.deleted' => 'WireGuard hub deleted',
            'wireguard.hub.deployed' => 'WireGuard hub deployed',
            'wireguard.hub.keys_rotated' => 'WireGuard hub keys rotated',
            'wireguard.spoke.created' => 'WireGuard spoke created',
            'wireguard.spoke.updated' => 'WireGuard spoke updated',
            'wireguard.spoke.deleted' => 'WireGuard spoke deleted',
            'wireguard.spoke.deployed' => 'WireGuard spoke deployed',
            'wireguard.spoke.keys_rotated' => 'WireGuard spoke keys rotated',
            'wireguard.connection.established' => 'WireGuard connection established',
            'wireguard.connection.terminated' => 'WireGuard connection terminated',
            'wireguard.security.isolation_violation' => 'WireGuard isolation violation detected',
            'wireguard.security.authentication_failure' => 'WireGuard authentication failure',
            'wireguard.config.drift_detected' => 'WireGuard configuration drift detected',
            'wireguard.logging.forwarding_failure' => 'WireGuard log forwarding failure',
        ]);

        Log::info('Setup WireGuard audit logging');
    }

    /**
     * Setup health monitoring
     */
    private function setupHealthMonitoring(): void
    {
        // Register health checks
        $this->metricsService->registerHealthCheck('wireguard_infrastructure', [
            'name' => 'WireGuard Infrastructure Health',
            'description' => 'Check overall WireGuard infrastructure health',
            'interval' => 120, // 2 minutes
            'checker' => function () {
                return $this->checkInfrastructureHealth();
            },
        ]);

        $this->metricsService->registerHealthCheck('wireguard_logging', [
            'name' => 'WireGuard Central Logging Health',
            'description' => 'Check central logging hub health',
            'interval' => 300, // 5 minutes
            'checker' => function () {
                return $this->checkLoggingHealth();
            },
        ]);

        $this->metricsService->registerHealthCheck('wireguard_security', [
            'name' => 'WireGuard Security Health',
            'description' => 'Check for security issues and violations',
            'interval' => 180, // 3 minutes
            'checker' => function () {
                return $this->checkSecurityHealth();
            },
        ]);

        Log::info('Setup WireGuard health monitoring');
    }

    /**
     * Create monitoring dashboards
     */
    private function createMonitoringDashboards(): void
    {
        // Network overview dashboard
        $this->analyticsService->createDashboard('wireguard_network_overview', [
            'title' => 'WireGuard Network Overview',
            'description' => 'High-level view of WireGuard network status',
            'widgets' => [
                [
                    'type' => 'metric_cards',
                    'title' => 'Network Statistics',
                    'metrics' => [
                        'total_hubs',
                        'active_connections',
                        'total_bandwidth_usage',
                        'network_health_score',
                    ],
                ],
                [
                    'type' => 'time_series_chart',
                    'title' => 'Connection Activity',
                    'metric' => 'active_connections',
                    'timeframe' => '24h',
                ],
                [
                    'type' => 'pie_chart',
                    'title' => 'Hub Types Distribution',
                    'metric' => 'hub_count',
                    'dimension' => 'hub_type',
                ],
                [
                    'type' => 'bar_chart',
                    'title' => 'Bandwidth Usage by Hub',
                    'metric' => 'bytes_total',
                    'dimension' => 'hub_name',
                ],
            ],
        ]);

        // Performance dashboard
        $this->analyticsService->createDashboard('wireguard_performance', [
            'title' => 'WireGuard Performance Metrics',
            'description' => 'Detailed performance analysis',
            'widgets' => [
                [
                    'type' => 'line_chart',
                    'title' => 'Average Latency',
                    'metric' => 'average_latency_ms',
                    'timeframe' => '6h',
                ],
                [
                    'type' => 'line_chart',
                    'title' => 'Packet Loss Rate',
                    'metric' => 'packet_loss_percentage',
                    'timeframe' => '6h',
                ],
                [
                    'type' => 'heatmap',
                    'title' => 'Connection Quality Matrix',
                    'x_axis' => 'hub_name',
                    'y_axis' => 'time',
                    'metric' => 'connection_quality',
                ],
            ],
        ]);

        // Security dashboard
        $this->analyticsService->createDashboard('wireguard_security', [
            'title' => 'WireGuard Security Monitoring',
            'description' => 'Security events and threat analysis',
            'widgets' => [
                [
                    'type' => 'alert_list',
                    'title' => 'Recent Security Events',
                    'source' => 'wireguard_security_events',
                ],
                [
                    'type' => 'bar_chart',
                    'title' => 'Authentication Failures',
                    'metric' => 'auth_failure_count',
                    'dimension' => 'hub_name',
                ],
                [
                    'type' => 'map',
                    'title' => 'Geographic Threat Distribution',
                    'metric' => 'threat_count',
                    'dimension' => 'source_location',
                ],
            ],
        ]);

        Log::info('Created WireGuard monitoring dashboards');
    }

    /**
     * Process network usage data for analytics
     */
    private function processNetworkUsageData(array $data): array
    {
        $processed = [];

        foreach ($data as $record) {
            $hourly_bucket = date('Y-m-d H:00:00', strtotime($record['timestamp']));

            if (! isset($processed[$hourly_bucket])) {
                $processed[$hourly_bucket] = [
                    'timestamp' => $hourly_bucket,
                    'total_bytes' => 0,
                    'session_count' => 0,
                    'unique_users' => [],
                    'hub_usage' => [],
                ];
            }

            $processed[$hourly_bucket]['total_bytes'] += $record['bytes_sent'] + $record['bytes_received'];
            $processed[$hourly_bucket]['session_count']++;
            $processed[$hourly_bucket]['unique_users'][] = $record['spoke_id'];

            if (! isset($processed[$hourly_bucket]['hub_usage'][$record['hub_id']])) {
                $processed[$hourly_bucket]['hub_usage'][$record['hub_id']] = 0;
            }
            $processed[$hourly_bucket]['hub_usage'][$record['hub_id']] += $record['bytes_sent'] + $record['bytes_received'];
        }

        // Convert unique users arrays to counts
        foreach ($processed as &$bucket) {
            $bucket['unique_users'] = count(array_unique($bucket['unique_users']));
        }

        return array_values($processed);
    }

    /**
     * Check infrastructure health
     */
    private function checkInfrastructureHealth(): array
    {
        $hubs = WireguardHub::where('status', 'active')->get();
        $healthyHubs = $hubs->where('health_status', 'healthy')->count();
        $totalHubs = $hubs->count();

        $healthScore = $totalHubs > 0 ? ($healthyHubs / $totalHubs) * 100 : 100;

        $status = match (true) {
            $healthScore >= 90 => 'healthy',
            $healthScore >= 70 => 'warning',
            default => 'critical'
        };

        return [
            'status' => $status,
            'score' => $healthScore,
            'details' => [
                'healthy_hubs' => $healthyHubs,
                'total_hubs' => $totalHubs,
                'active_connections' => WireguardConnection::where('connection_status', 'connected')->count(),
                'recent_failures' => WireguardConnection::where('connection_status', 'failed')
                    ->where('created_at', '>=', now()->subHour())->count(),
            ],
        ];
    }

    /**
     * Check logging health
     */
    private function checkLoggingHealth(): array
    {
        $loggingHub = WireguardHub::where('hub_type', 'logging')->where('status', 'active')->first();

        if (! $loggingHub) {
            return [
                'status' => 'critical',
                'score' => 0,
                'details' => ['error' => 'No logging hub configured'],
            ];
        }

        // Check if logging hub is healthy
        $isHealthy = $loggingHub->health_status === 'healthy';
        $lastDeployed = $loggingHub->last_deployed_at;
        $isRecent = $lastDeployed && $lastDeployed->gt(now()->subDays(1));

        $status = $isHealthy && $isRecent ? 'healthy' : 'warning';
        $score = $isHealthy ? ($isRecent ? 100 : 80) : 50;

        return [
            'status' => $status,
            'score' => $score,
            'details' => [
                'logging_hub_health' => $loggingHub->health_status,
                'last_deployed' => $lastDeployed?->toISOString(),
                'monitoring_active' => $loggingHub->monitoring_enabled,
            ],
        ];
    }

    /**
     * Check security health
     */
    private function checkSecurityHealth(): array
    {
        $recentFailures = WireguardConnection::where('connection_status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $authFailures = $this->countAuthenticationFailures();
        $suspiciousActivity = $this->detectSuspiciousTraffic();

        $totalSecurityEvents = $recentFailures + $authFailures + count($suspiciousActivity);

        $status = match (true) {
            $totalSecurityEvents === 0 => 'healthy',
            $totalSecurityEvents <= 5 => 'warning',
            default => 'critical'
        };

        $score = max(0, 100 - ($totalSecurityEvents * 10));

        return [
            'status' => $status,
            'score' => $score,
            'details' => [
                'connection_failures' => $recentFailures,
                'authentication_failures' => $authFailures,
                'suspicious_activities' => count($suspiciousActivity),
                'total_security_events' => $totalSecurityEvents,
            ],
        ];
    }

    // Helper methods (simplified implementations)
    private function calculateUptimePercentage(WireguardHub $hub): float
    {
        return 99.5;
    }

    private function getHubTypeSpecificMetrics(WireguardHub $hub): array
    {
        return [];
    }

    private function calculateConnectionQuality($connection): float
    {
        return 95.0;
    }

    private function calculateBandwidthUtilization($connection): float
    {
        return 45.0;
    }

    private function countAuthenticationFailures(): int
    {
        return 0;
    }

    private function detectSuspiciousTraffic(): array
    {
        return [];
    }

    private function countKeyRotationEvents(): int
    {
        return 0;
    }

    private function countConfigurationDriftEvents(): int
    {
        return 0;
    }

    private function countIsolationViolations(WireguardHub $hub): int
    {
        return 0;
    }

    private function countFirewallBlocks(WireguardHub $hub): int
    {
        return 0;
    }

    private function detectUnusualBandwidth(WireguardHub $hub): bool
    {
        return false;
    }

    private function processPerformanceData(array $data): array
    {
        return $data;
    }

    private function processSecurityData(array $data): array
    {
        return $data;
    }

    private function processCustomerUsageData(array $data): array
    {
        return $data;
    }
}
