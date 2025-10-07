<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Management Settings
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the NS Config package.
    |
    */

    'templates' => [
        'path' => storage_path('config-templates'),
        'formats' => ['yaml', 'json', 'toml', 'ini'],
        'validation' => true,
    ],

    'deployment' => [
        'dry_run' => false,
        'backup_before_deploy' => true,
        'rollback_on_failure' => true,
        'max_rollback_versions' => 5,
    ],

    'versioning' => [
        'enabled' => true,
        'storage' => 'database',
        'retention_days' => 90,
    ],

    'security' => [
        'encrypt_sensitive' => true,
        'audit_changes' => true,
        'require_approval' => false,
    ],

    'synchronization' => [
        'enabled' => true,
        'sources' => [],
        'interval' => 3600, // 1 hour
    ],
];
