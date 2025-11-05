<?php

declare(strict_types=1);

namespace NetServa\Cms\Support;

/**
 * Settings Manager
 *
 * Provides progressive enhancement for CMS settings:
 * - Standalone mode: Uses config files + .env (read-only)
 * - Enhanced mode: Uses Spatie Settings (database, CRUD UI)
 *
 * Auto-detects which mode is available and falls back gracefully.
 */
class SettingsManager
{
    protected static ?bool $hasSpatie = null;

    /**
     * Get setting value with automatic fallback
     *
     * @param  string  $key  The setting key (e.g., 'name', 'tagline')
     * @param  mixed  $default  Default value if setting not found
     * @return mixed The setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check if Spatie Settings is available
        if (static::hasSpatieSettings()) {
            return static::getSpatieValue($key, $default);
        }

        // Fallback to config file
        return config("netserva-cms.{$key}", $default);
    }

    /**
     * Set setting value (only works with Spatie)
     *
     * @param  string  $key  The setting key
     * @param  mixed  $value  The value to set
     *
     * @throws \RuntimeException if Spatie Settings not available
     */
    public static function set(string $key, mixed $value): void
    {
        if (static::hasSpatieSettings()) {
            static::setSpatieValue($key, $value);
        } else {
            throw new \RuntimeException(
                'Settings are read-only in standalone mode. '.
                'Install netserva/core for CRUD functionality.'
            );
        }
    }

    /**
     * Check if enhanced mode is available
     */
    public static function isEnhanced(): bool
    {
        return static::hasSpatieSettings();
    }

    /**
     * Check if Spatie Settings is available
     */
    protected static function hasSpatieSettings(): bool
    {
        if (static::$hasSpatie === null) {
            static::$hasSpatie = class_exists(\Spatie\LaravelSettings\Settings::class)
                && class_exists(\NetServa\Cms\Settings\CmsSettings::class);
        }

        return static::$hasSpatie;
    }

    /**
     * Get value from Spatie Settings
     */
    protected static function getSpatieValue(string $key, mixed $default): mixed
    {
        try {
            $settings = app(\NetServa\Cms\Settings\CmsSettings::class);

            return $settings->{$key} ?? $default;
        } catch (\Exception $e) {
            // Fallback to config if settings not yet initialized
            return config("netserva-cms.{$key}", $default);
        }
    }

    /**
     * Set value in Spatie Settings
     */
    protected static function setSpatieValue(string $key, mixed $value): void
    {
        $settings = app(\NetServa\Cms\Settings\CmsSettings::class);
        $settings->{$key} = $value;
        $settings->save();
    }

    /**
     * Reset cache (useful for testing)
     */
    public static function resetCache(): void
    {
        static::$hasSpatie = null;
    }
}
