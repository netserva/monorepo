<?php

declare(strict_types=1);

namespace NetServa\Cms\Support;

/**
 * Settings Manager
 *
 * Provides progressive enhancement for CMS settings:
 * - Standalone mode: Uses config files + .env (read-only)
 * - Enhanced mode: Uses NetServa Core Setting model (database, CRUD UI)
 *
 * Auto-detects which mode is available and falls back gracefully.
 */
class SettingsManager
{
    protected static ?bool $hasCore = null;

    /**
     * Get setting value with automatic fallback
     *
     * @param  string  $key  The setting key (e.g., 'name', 'tagline')
     * @param  mixed  $default  Default value if setting not found
     * @return mixed The setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check if NetServa Core is available
        if (static::hasCoreSettings()) {
            return static::getCoreValue($key, $default);
        }

        // Fallback to config file
        return config("netserva-cms.{$key}", $default);
    }

    /**
     * Set setting value (only works with Core)
     *
     * @param  string  $key  The setting key
     * @param  mixed  $value  The value to set
     *
     * @throws \RuntimeException if Core Settings not available
     */
    public static function set(string $key, mixed $value): void
    {
        if (static::hasCoreSettings()) {
            static::setCoreValue($key, $value);
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
        return static::hasCoreSettings();
    }

    /**
     * Check if NetServa Core Settings is available
     */
    protected static function hasCoreSettings(): bool
    {
        if (static::$hasCore === null) {
            static::$hasCore = class_exists(\NetServa\Core\Models\Setting::class);
        }

        return static::$hasCore;
    }

    /**
     * Get value from Core Settings
     */
    protected static function getCoreValue(string $key, mixed $default): mixed
    {
        try {
            // Try cms.* key first
            $fullKey = "cms.{$key}";
            $value = \NetServa\Core\Models\Setting::getValue($fullKey, null);

            // If not found, fallback to app.* for global settings (name, tagline only)
            if ($value === null && in_array($key, ['name', 'tagline'])) {
                $value = \NetServa\Core\Models\Setting::getValue("app.{$key}", null);
            }

            return $value ?? $default;
        } catch (\Exception $e) {
            // Fallback to config if settings not yet initialized
            return config("netserva-cms.{$key}", $default);
        }
    }

    /**
     * Set value in Core Settings
     */
    protected static function setCoreValue(string $key, mixed $value): void
    {
        $fullKey = "cms.{$key}";
        \NetServa\Core\Models\Setting::setValue($fullKey, $value, 'cms');
    }

    /**
     * Reset cache (useful for testing)
     */
    public static function resetCache(): void
    {
        static::$hasCore = null;
    }
}
