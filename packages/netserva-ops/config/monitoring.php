<?php

return [
    'health_checks' => [
        'enabled' => true,
        'interval' => 60, // seconds
        'timeout' => 30,
        'retry_attempts' => 3,
    ],

    'metrics' => [
        'system' => ['cpu', 'memory', 'disk', 'network'],
        'services' => ['nginx', 'mysql', 'php-fpm'],
        'custom' => [],
    ],

    'alerts' => [
        'enabled' => true,
        'channels' => ['email', 'slack'],
        'thresholds' => [
            'cpu' => 80,
            'memory' => 85,
            'disk' => 90,
        ],
    ],

    'retention' => [
        'metrics' => 90, // days
        'logs' => 30,
        'alerts' => 365,
    ],

    'dashboards' => [
        'auto_refresh' => true,
        'refresh_interval' => 30,
        'default_timeframe' => '24h',
    ],
];
