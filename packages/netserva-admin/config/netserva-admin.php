<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group name for admin resources in Filament.
    |
    */
    'navigation_group' => 'Administration',

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Configure which resources are enabled.
    |
    */
    'resources' => [
        'settings' => true,
        'plugins' => true,
        'audit_logs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Settings management configuration.
    |
    */
    'settings' => [
        'per_page' => 25,
        'allowed_types' => ['string', 'integer', 'boolean', 'json'],
        'default_type' => 'string',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Plugin management configuration.
    |
    */
    'plugins' => [
        'per_page' => 25,
        'show_composer_data' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    |
    | Audit log configuration.
    |
    */
    'audit_logs' => [
        'per_page' => 50,
        'retention_days' => 90,
    ],
];
