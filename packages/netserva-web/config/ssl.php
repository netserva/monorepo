<?php

return [
    'providers' => [
        'letsencrypt' => [
            'enabled' => true,
            'directory_url' => 'https://acme-v02.api.letsencrypt.org/directory',
            'staging_url' => 'https://acme-staging-v02.api.letsencrypt.org/directory',
        ],
        'zerossl' => [
            'enabled' => false,
            'directory_url' => 'https://acme.zerossl.com/v2/DV90',
        ],
    ],

    'acme' => [
        'challenge_type' => 'http-01',
        'key_type' => 'rsa',
        'key_size' => 2048,
        'auto_renewal' => true,
        'renewal_days_before' => 30,
    ],

    'storage' => [
        'certificates_path' => '/etc/ssl/certs',
        'private_keys_path' => '/etc/ssl/private',
        'backup_enabled' => true,
    ],

    'security' => [
        'hsts_enabled' => true,
        'hsts_max_age' => 31536000,
        'enforce_https' => true,
    ],
];
