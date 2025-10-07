<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NS Analytics - Simplified Configuration
    |--------------------------------------------------------------------------
    */

    'collection' => [
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'default_frequency' => 'hourly',
    ],

    'visualizations' => [
        'default_refresh_interval' => 300, // 5 minutes
        'supported_types' => ['line', 'bar', 'pie', 'table', 'metric'],
        'default_colors' => [
            '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8',
        ],
    ],

    'dashboards' => [
        'default_columns' => 12,
        'max_widgets' => 20,
        'default_refresh_interval' => 300,
    ],

    'alerts' => [
        'enabled_channels' => ['email', 'slack'],
        'cooldown_minutes' => 15,
        'max_recipients' => 10,
    ],

    'data_sources' => [
        'supported_types' => ['database', 'api', 'csv'],
        'connection_timeout' => 10,
        'query_timeout' => 30,
    ],

];
