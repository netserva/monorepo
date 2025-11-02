<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CMS General Settings
    |--------------------------------------------------------------------------
    */

    'name' => env('CMS_NAME', 'NetServa CMS'),
    'description' => env('CMS_DESCRIPTION', 'Professional Laravel CMS'),

    /*
    |--------------------------------------------------------------------------
    | Frontend Settings
    |--------------------------------------------------------------------------
    */

    'frontend' => [
        'enabled' => env('CMS_FRONTEND_ENABLED', true),
        'theme' => env('CMS_FRONTEND_THEME', 'default'),
        'posts_per_page' => env('CMS_POSTS_PER_PAGE', 10),
        'cache_enabled' => env('CMS_CACHE_ENABLED', true),
        'cache_ttl' => env('CMS_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Settings
    |--------------------------------------------------------------------------
    */

    'seo' => [
        'default_meta_title' => env('CMS_DEFAULT_META_TITLE', 'NetServa CMS'),
        'default_meta_description' => env('CMS_DEFAULT_META_DESCRIPTION', ''),
        'site_name' => env('CMS_SITE_NAME', 'NetServa CMS'),
        'twitter_handle' => env('CMS_TWITTER_HANDLE', ''),
        'og_image' => env('CMS_OG_IMAGE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Settings
    |--------------------------------------------------------------------------
    */

    'media' => [
        'disk' => env('CMS_MEDIA_DISK', 'public'),
        'max_file_size' => env('CMS_MAX_FILE_SIZE', 10240), // KB
        'allowed_mimes' => [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
        ],
        'image_conversions' => [
            'thumb' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 800, 'height' => null],
            'large' => ['width' => 1200, 'height' => null],
        ],
        'webp_conversion' => env('CMS_WEBP_CONVERSION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Templates
    |--------------------------------------------------------------------------
    */

    'templates' => [
        'default' => 'Default',
        'homepage' => 'Homepage',
        'pricing' => 'Pricing',
        'contact' => 'Contact',
        'blank' => 'Blank (No Sidebar)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blog Settings
    |--------------------------------------------------------------------------
    */

    'blog' => [
        'enabled' => env('CMS_BLOG_ENABLED', true),
        'route_prefix' => env('CMS_BLOG_PREFIX', 'blog'),
        'posts_per_page' => env('CMS_BLOG_POSTS_PER_PAGE', 6),
        'categories_enabled' => env('CMS_CATEGORIES_ENABLED', true),
        'tags_enabled' => env('CMS_TAGS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Portfolio Settings
    |--------------------------------------------------------------------------
    */

    'portfolio' => [
        'enabled' => env('CMS_PORTFOLIO_ENABLED', true),
        'route_prefix' => env('CMS_PORTFOLIO_PREFIX', 'portfolio'),
    ],
];
