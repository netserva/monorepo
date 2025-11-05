<?php

declare(strict_types=1);

namespace NetServa\Cms\Settings;

// Only define this class if Spatie Settings is available
if (! class_exists(\Spatie\LaravelSettings\Settings::class)) {
    return;
}

use Spatie\LaravelSettings\Settings;

/**
 * CMS Settings
 *
 * Database-backed settings for NetServa CMS.
 * Defaults are populated from config/netserva-cms.php during migration.
 *
 * @property string $name Site name
 * @property string $tagline Site tagline/slogan
 * @property string $description Site description
 * @property string|null $logo_url Logo file URL
 * @property string|null $favicon_url Favicon URL
 * @property string|null $contact_email Contact email address
 * @property string|null $contact_phone Contact phone number
 * @property string|null $contact_address Physical address
 * @property string $timezone Default timezone
 * @property string $locale Default locale
 */
class CmsSettings extends Settings
{
    // Site Identity
    public string $name;

    public string $tagline;

    public string $description;

    public ?string $logo_url;

    public ?string $favicon_url;

    // Contact Information
    public ?string $contact_email;

    public ?string $contact_phone;

    public ?string $contact_address;

    // Localization
    public string $timezone;

    public string $locale;

    /**
     * Settings group name (stored as 'cms.*' in database)
     */
    public static function group(): string
    {
        return 'cms';
    }

    /**
     * Get default values from config files
     *
     * Used during initial migration to populate settings
     * from existing config/netserva-cms.php values
     */
    public static function defaults(): array
    {
        return [
            // Site Identity
            'name' => config('netserva-cms.name', 'NetServa'),
            'tagline' => config('netserva-cms.tagline', 'Server Management Platform'),
            'description' => config('netserva-cms.description', 'Modern server management platform built on Laravel 12 and Filament 4'),
            'logo_url' => null,
            'favicon_url' => null,

            // Contact Information
            'contact_email' => config('netserva-cms.seo.contact_info') ? static::extractEmail(config('netserva-cms.seo.contact_info')) : null,
            'contact_phone' => null,
            'contact_address' => null,

            // Localization
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
        ];
    }

    /**
     * Extract email from contact info string (helper for migration)
     */
    protected static function extractEmail(?string $contactInfo): ?string
    {
        if (! $contactInfo) {
            return null;
        }

        // Simple email extraction
        preg_match('/[\w\-\.]+@[\w\-\.]+\.\w+/', $contactInfo, $matches);

        return $matches[0] ?? null;
    }
}
