<?php

declare(strict_types=1);

use NetServa\Cms\Support\SettingsManager;

if (! function_exists('cms_setting')) {
    /**
     * Get a CMS setting value
     *
     * This helper provides progressive enhancement:
     * - Standalone mode: Returns values from config/netserva-cms.php + .env
     * - Enhanced mode: Returns values from database (when netserva-core installed)
     *
     * @param  string  $key  Setting key (e.g., 'name', 'tagline', 'description')
     * @param  mixed  $default  Default value if setting not found
     * @return mixed The setting value
     *
     * @example
     * // Get site name
     * cms_setting('name') // "NetServa"
     *
     * // Get tagline with fallback
     * cms_setting('tagline', 'Default Tagline')
     */
    function cms_setting(string $key, mixed $default = null): mixed
    {
        return SettingsManager::get($key, $default);
    }
}

if (! function_exists('cms_settings_enhanced')) {
    /**
     * Check if enhanced settings mode is available
     *
     * @return bool True if netserva-core is installed and Spatie Settings available
     *
     * @example
     * @if (cms_settings_enhanced())
     *     <p>You can edit settings in the admin panel</p>
     * @endif
     */
    function cms_settings_enhanced(): bool
    {
        return SettingsManager::isEnhanced();
    }
}
