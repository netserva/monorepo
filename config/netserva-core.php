<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NetServa Core Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the NetServa Core package.
    | These settings control the behavior of the plugin system, services,
    | and other core functionality.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Plugin System
    |--------------------------------------------------------------------------
    |
    | Configuration for the NetServa plugin system including discovery,
    | caching, and dependency resolution.
    |
    */
    'plugins' => [
        'cache_ttl' => env('NETSERVA_PLUGIN_CACHE_TTL', 300),
        'auto_discovery' => env('NETSERVA_PLUGIN_AUTO_DISCOVERY', true),
        'discovery_paths' => [
            'packages/*/src/*Plugin.php',
            'vendor/netserva/*/src/*Plugin.php',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for NetServa logging including log levels, channels,
    | and formatting options.
    |
    */
    'logging' => [
        'default_level' => env('NETSERVA_LOG_LEVEL', 'info'),
        'channel' => env('NETSERVA_LOG_CHANNEL', 'netserva'),
        'include_context' => env('NETSERVA_LOG_INCLUDE_CONTEXT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    |
    | Configuration for core NetServa services including timeouts,
    | retry attempts, and service-specific settings.
    |
    */
    'services' => [
        'default_timeout' => env('NETSERVA_SERVICE_TIMEOUT', 30),
        'max_retry_attempts' => env('NETSERVA_SERVICE_MAX_RETRIES', 3),
        'retry_delay' => env('NETSERVA_SERVICE_RETRY_DELAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security-related configuration including encryption, authentication,
    | and access control settings.
    |
    */
    'security' => [
        'enable_audit_logging' => env('NETSERVA_ENABLE_AUDIT_LOGGING', true),
        'encrypt_sensitive_data' => env('NETSERVA_ENCRYPT_SENSITIVE_DATA', true),
        'password_min_length' => env('NETSERVA_PASSWORD_MIN_LENGTH', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance-related configuration including caching, optimization,
    | and resource management settings.
    |
    */
    'performance' => [
        'enable_caching' => env('NETSERVA_ENABLE_CACHING', true),
        'cache_driver' => env('NETSERVA_CACHE_DRIVER', 'redis'),
        'cache_prefix' => env('NETSERVA_CACHE_PREFIX', 'netserva'),
    ],
];
