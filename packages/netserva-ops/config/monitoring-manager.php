<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    | Core monitoring settings and default values
    */
    'enabled' => env('MONITORING_ENABLED', true),

    'check_interval' => env('MONITORING_CHECK_INTERVAL', 60), // seconds

    'metric_retention_days' => env('MONITORING_METRIC_RETENTION', 90),

    'alert_cooldown_minutes' => env('MONITORING_ALERT_COOLDOWN', 15),

    /*
    |--------------------------------------------------------------------------
    | Check Types
    |--------------------------------------------------------------------------
    | Available monitoring check types and their handlers
    */
    'check_types' => [
        'ping' => [
            'name' => 'Ping Check',
            'handler' => \NetServa\Ops\Checks\PingCheck::class,
            'timeout' => 5,
        ],

        'http' => [
            'name' => 'HTTP/HTTPS Check',
            'handler' => \NetServa\Ops\Checks\HttpCheck::class,
            'timeout' => 30,
        ],

        'tcp' => [
            'name' => 'TCP Port Check',
            'handler' => \NetServa\Ops\Checks\TcpCheck::class,
            'timeout' => 10,
        ],

        'ssl' => [
            'name' => 'SSL Certificate Check',
            'handler' => \NetServa\Ops\Checks\SslCheck::class,
            'timeout' => 10,
        ],

        'dns' => [
            'name' => 'DNS Resolution Check',
            'handler' => \NetServa\Ops\Checks\DnsCheck::class,
            'timeout' => 5,
        ],

        'disk' => [
            'name' => 'Disk Usage Check',
            'handler' => \NetServa\Ops\Checks\DiskCheck::class,
            'timeout' => 10,
        ],

        'memory' => [
            'name' => 'Memory Usage Check',
            'handler' => \NetServa\Ops\Checks\MemoryCheck::class,
            'timeout' => 5,
        ],

        'cpu' => [
            'name' => 'CPU Usage Check',
            'handler' => \NetServa\Ops\Checks\CpuCheck::class,
            'timeout' => 5,
        ],

        'service' => [
            'name' => 'System Service Check',
            'handler' => \NetServa\Ops\Checks\ServiceCheck::class,
            'timeout' => 10,
        ],

        'database' => [
            'name' => 'Database Connection Check',
            'handler' => \NetServa\Ops\Checks\DatabaseCheck::class,
            'timeout' => 15,
        ],

        'docker' => [
            'name' => 'Docker Container Check',
            'handler' => \NetServa\Ops\Checks\DockerCheck::class,
            'timeout' => 10,
        ],

        'custom' => [
            'name' => 'Custom Script Check',
            'handler' => \NetServa\Ops\Checks\CustomCheck::class,
            'timeout' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metric Collectors
    |--------------------------------------------------------------------------
    | System metrics to collect and their intervals
    */
    'metric_collectors' => [
        'system' => [
            'handler' => \NetServa\Ops\Collectors\SystemMetricCollector::class,
            'interval' => 60, // seconds
            'metrics' => ['cpu', 'memory', 'disk', 'network', 'load'],
        ],

        'application' => [
            'handler' => \NetServa\Ops\Collectors\ApplicationMetricCollector::class,
            'interval' => 300,
            'metrics' => ['requests', 'response_time', 'errors', 'queue_size'],
        ],

        'database' => [
            'handler' => \NetServa\Ops\Collectors\DatabaseMetricCollector::class,
            'interval' => 300,
            'metrics' => ['connections', 'queries', 'slow_queries', 'replication_lag'],
        ],

        'container' => [
            'handler' => \NetServa\Ops\Collectors\ContainerMetricCollector::class,
            'interval' => 120,
            'metrics' => ['container_count', 'container_cpu', 'container_memory'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Channels
    |--------------------------------------------------------------------------
    | Available notification channels for alerts
    */
    'alert_channels' => [
        'mail' => [
            'driver' => 'mail',
            'to' => env('MONITORING_ALERT_EMAIL'),
        ],

        'slack' => [
            'driver' => 'slack',
            'webhook_url' => env('MONITORING_SLACK_WEBHOOK'),
            'channel' => env('MONITORING_SLACK_CHANNEL', '#alerts'),
        ],

        'discord' => [
            'driver' => 'discord',
            'webhook_url' => env('MONITORING_DISCORD_WEBHOOK'),
        ],

        'webhook' => [
            'driver' => 'webhook',
            'url' => env('MONITORING_WEBHOOK_URL'),
            'method' => 'POST',
            'headers' => [],
        ],

        'sms' => [
            'driver' => 'sms',
            'provider' => env('MONITORING_SMS_PROVIDER', 'twilio'),
            'to' => env('MONITORING_SMS_NUMBER'),
        ],

        'pushover' => [
            'driver' => 'pushover',
            'user_key' => env('PUSHOVER_USER_KEY'),
            'api_token' => env('PUSHOVER_API_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Severity Levels
    |--------------------------------------------------------------------------
    | Define severity levels and their characteristics
    */
    'severity_levels' => [
        'critical' => [
            'priority' => 1,
            'color' => '#dc2626', // red-600
            'notify_immediately' => true,
            'escalate_after_minutes' => 5,
        ],

        'high' => [
            'priority' => 2,
            'color' => '#ea580c', // orange-600
            'notify_immediately' => true,
            'escalate_after_minutes' => 15,
        ],

        'medium' => [
            'priority' => 3,
            'color' => '#f59e0b', // amber-500
            'notify_immediately' => false,
            'escalate_after_minutes' => 30,
        ],

        'low' => [
            'priority' => 4,
            'color' => '#3b82f6', // blue-500
            'notify_immediately' => false,
            'escalate_after_minutes' => 60,
        ],

        'info' => [
            'priority' => 5,
            'color' => '#6b7280', // gray-500
            'notify_immediately' => false,
            'escalate_after_minutes' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Page Configuration
    |--------------------------------------------------------------------------
    | Public status page settings
    */
    'status_page' => [
        'enabled' => env('STATUS_PAGE_ENABLED', true),
        'public' => env('STATUS_PAGE_PUBLIC', true),
        'url' => env('STATUS_PAGE_URL', '/status'),
        'title' => env('STATUS_PAGE_TITLE', 'System Status'),
        'refresh_interval' => 30, // seconds
        'show_metrics' => true,
        'show_incidents' => true,
        'incident_history_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    | Default thresholds for performance metrics
    */
    'thresholds' => [
        'cpu' => [
            'warning' => 70,
            'critical' => 90,
        ],

        'memory' => [
            'warning' => 80,
            'critical' => 95,
        ],

        'disk' => [
            'warning' => 80,
            'critical' => 90,
        ],

        'response_time' => [
            'warning' => 1000, // ms
            'critical' => 3000,
        ],

        'error_rate' => [
            'warning' => 1, // percentage
            'critical' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Windows
    |--------------------------------------------------------------------------
    | Define maintenance windows where alerts are suppressed
    */
    'maintenance_windows' => [
        'enabled' => env('MONITORING_MAINTENANCE_MODE', false),
        'suppress_alerts' => true,
        'show_on_status_page' => true,
    ],
];
