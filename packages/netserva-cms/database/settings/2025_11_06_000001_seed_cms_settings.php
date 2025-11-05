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
            // Site Identity
            'name' => config('netserva-cms.name', 'NetServa'),
            'tagline' => config('netserva-cms.tagline', 'Server Management Platform'),
            'description' => config('netserva-cms.description', 'Modern server management platform built on Laravel 12 and Filament 4'),
            'logo_url' => null,
            'favicon_url' => null,

            // Contact Information
            'contact_email' => null,
            'contact_phone' => null,
            'contact_address' => null,

            // Localization
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
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
