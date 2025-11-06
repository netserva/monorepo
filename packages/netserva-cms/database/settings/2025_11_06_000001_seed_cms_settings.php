<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Create CMS Settings Migration
 *
 * Populates the netserva_settings table with default CMS values from config files.
 * This migration only runs when netserva-core (with Setting model) is installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only run if NetServa Core is installed
        if (! class_exists(\NetServa\Core\Models\Setting::class)) {
            return;
        }

        // Get defaults from config
        $defaults = [
            // Site Identity (will fallback to app.name/app.tagline if not set)
            'description' => config('netserva-cms.description', 'Modern server management platform built on Laravel 12 and Filament 4'),
            'logo_url' => null,
            'favicon_url' => null,

            // Contact Information
            'contact_email' => null,
            'contact_phone' => null,
            'contact_address' => null,

            // SEO Meta Tags
            'seo_title_template' => '{page_title} | {site_name}',
            'seo_description' => config('netserva-cms.description', 'Modern server management platform built on Laravel 12 and Filament 4'),
            'seo_keywords' => 'server management, hosting, web hosting, infrastructure',
            'seo_author' => null,

            // Open Graph / Social Media
            'og_image' => null,
            'og_type' => 'website',
            'twitter_handle' => null,
            'twitter_card' => 'summary_large_image',

            // Content Settings
            'posts_per_page' => 10,
            'enable_comments' => true,
            'theme_config' => json_encode([
                'primary_color' => '#dc2626',
                'font_family' => 'Inter',
            ]),
        ];

        // Create each setting in the database
        foreach ($defaults as $key => $value) {
            \NetServa\Core\Models\Setting::setValue("cms.{$key}", $value, 'cms');
        }
    }

    public function down(): void
    {
        // Only run if NetServa Core is installed
        if (! class_exists(\NetServa\Core\Models\Setting::class)) {
            return;
        }

        // Remove all CMS settings
        \NetServa\Core\Models\Setting::where('category', 'cms')->delete();
    }
};
