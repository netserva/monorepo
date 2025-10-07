<?php

return [
    'schedule' => [
        'enabled' => true,
        'frequency' => 'daily',
        'time' => '02:00',
        'timezone' => 'UTC',
    ],

    'storage' => [
        'local' => storage_path('backups'),
        'remote' => [
            'enabled' => false,
            'disk' => 's3',
            'path' => 'backups',
        ],
    ],

    'retention' => [
        'daily' => 7,
        'weekly' => 4,
        'monthly' => 12,
        'yearly' => 5,
    ],

    'compression' => [
        'enabled' => true,
        'algorithm' => 'gzip',
        'level' => 6,
    ],

    'encryption' => [
        'enabled' => false,
        'algorithm' => 'aes-256-cbc',
    ],

    'monitoring' => [
        'alerts' => true,
        'notifications' => ['email'],
    ],
];
