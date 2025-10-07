<?php

return [
    'servers' => [
        'nginx' => [
            'enabled' => true,
            'config_path' => '/etc/nginx',
            'sites_available' => '/etc/nginx/sites-available',
            'sites_enabled' => '/etc/nginx/sites-enabled',
        ],
        'apache' => [
            'enabled' => false,
            'config_path' => '/etc/apache2',
            'sites_available' => '/etc/apache2/sites-available',
            'sites_enabled' => '/etc/apache2/sites-enabled',
        ],
    ],

    'php' => [
        'versions' => ['8.3', '8.2', '8.1'],
        'default_version' => '8.3',
        'fpm_pool_template' => 'default',
    ],

    'ssl' => [
        'auto_redirect' => true,
        'hsts_enabled' => true,
        'acme_challenge_path' => '/.well-known/acme-challenge',
    ],

    'monitoring' => [
        'enabled' => true,
        'metrics' => ['response_time', 'status_codes', 'traffic'],
    ],
];
