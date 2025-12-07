<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CRM Configuration
    |--------------------------------------------------------------------------
    |
    | NetServa CRM is a standalone customer relationship management package
    | that can optionally integrate with other NetServa packages when present.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Customer Type
    |--------------------------------------------------------------------------
    |
    | The default type for new customers. Can be 'company' or 'individual'.
    |
    */
    'default_type' => 'company',

    /*
    |--------------------------------------------------------------------------
    | Default Customer Status
    |--------------------------------------------------------------------------
    |
    | The default status for new customers.
    | Options: 'active', 'prospect', 'suspended', 'cancelled'
    |
    */
    'default_status' => 'active',

    /*
    |--------------------------------------------------------------------------
    | Default Country
    |--------------------------------------------------------------------------
    |
    | The default country code for new customers (ISO 3166-1 alpha-2).
    |
    */
    'default_country' => 'AU',

    /*
    |--------------------------------------------------------------------------
    | Enable Fleet Integration
    |--------------------------------------------------------------------------
    |
    | When true AND netserva/fleet is installed, customers can be linked
    | to VSites, VNodes, and VHosts.
    |
    */
    'enable_fleet_integration' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable Domain Integration
    |--------------------------------------------------------------------------
    |
    | When true AND SwDomain model exists, customers can be linked to domains.
    |
    */
    'enable_domain_integration' => true,

    /*
    |--------------------------------------------------------------------------
    | Slug Generation
    |--------------------------------------------------------------------------
    |
    | Configure how customer slugs are generated.
    |
    */
    'slug' => [
        'source' => 'name',           // Field to generate slug from
        'max_length' => 50,           // Maximum slug length
        'separator' => '-',           // Separator character
        'unique' => true,             // Ensure uniqueness
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default pagination settings for customer listings.
    |
    */
    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | When true, customers are soft-deleted and can be restored.
    |
    */
    'soft_deletes' => true,
];
