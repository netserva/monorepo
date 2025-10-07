<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Management Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the NS Database package.
    |
    */

    'servers' => [
        'management' => [
            'auto_detect' => true,
            'supported_engines' => ['mysql', 'mariadb', 'postgresql', 'sqlite'],
        ],
        'monitoring' => [
            'enabled' => true,
            'metrics' => ['connections', 'queries', 'slow_queries', 'storage'],
        ],
    ],

    'backup' => [
        'enabled' => true,
        'schedule' => 'daily',
        'retention_days' => 30,
        'compression' => true,
        'encryption' => false,
    ],

    'optimization' => [
        'auto_optimize' => false,
        'index_analysis' => true,
        'query_analysis' => true,
        'maintenance_window' => '02:00-04:00',
    ],

    'replication' => [
        'monitoring' => true,
        'lag_threshold' => 30, // seconds
        'auto_failover' => false,
    ],

    'security' => [
        'audit_queries' => false,
        'block_dangerous_queries' => true,
        'connection_encryption' => true,
    ],
];
