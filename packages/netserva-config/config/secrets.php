<?php

return [
    'encryption' => [
        'algorithm' => 'aes-256-gcm',
        'key_rotation' => [
            'enabled' => true,
            'interval' => 30, // days
        ],
    ],

    'storage' => [
        'backend' => 'database',
        'vault_integration' => false,
        'backup_enabled' => true,
    ],

    'access_control' => [
        'rbac_enabled' => true,
        'audit_access' => true,
        'session_timeout' => 15, // minutes
    ],

    'security' => [
        'two_factor_required' => false,
        'ip_restrictions' => [],
        'auto_expire_secrets' => false,
    ],

    'monitoring' => [
        'access_logging' => true,
        'anomaly_detection' => true,
        'alert_on_access' => false,
    ],
];
