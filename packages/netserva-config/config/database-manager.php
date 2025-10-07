<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Manager Configuration
    |--------------------------------------------------------------------------
    | Core database management settings
    */
    'enabled' => env('DATABASE_MANAGER_ENABLED', true),

    'default_engine' => env('DEFAULT_DATABASE_ENGINE', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Engine Types
    |--------------------------------------------------------------------------
    | Supported database engines and their configurations
    */
    'database_engines' => [
        'mysql' => [
            'name' => 'MySQL',
            'default_port' => 3306,
            'service_name' => 'mysql',
        ],

        'mariadb' => [
            'name' => 'MariaDB',
            'default_port' => 3306,
            'service_name' => 'mariadb',
        ],

        'postgresql' => [
            'name' => 'PostgreSQL',
            'default_port' => 5432,
            'service_name' => 'postgresql',
        ],

        'sqlite' => [
            'name' => 'SQLite',
            'default_port' => null,
            'service_name' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    | Basic connection configuration
    */
    'connection' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    | Basic security settings
    */
    'security' => [
        'ssl_enabled' => env('DB_SSL_ENABLED', false),
        'encrypt_passwords' => true,
    ],
];
