<?php

declare(strict_types=1);

use NetServa\Cms\Models\Theme as ThemeModel;
use NetServa\Cms\Services\ThemeService;
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
     *
     * @if (cms_settings_enhanced())
     *     <p>You can edit settings in the admin panel</p>
     *
     * @endif
     */
    function cms_settings_enhanced(): bool
    {
        return SettingsManager::isEnhanced();
    }
}

if (! function_exists('theme')) {
    /**
     * Get theme instance or theme setting value
     *
     * @param  string|null  $key  Setting key (e.g., 'colors.primary')
     * @param  mixed  $default  Default value if setting not found
     * @return mixed|ThemeModel
     *
     * @example
     * // Get active theme instance
     * theme() // Theme model instance
     *
     * // Get theme setting
     * theme('colors.primary') // "#DC2626"
     *
     * // Get setting with fallback
     * theme('colors.custom', '#000000')
     */
    function theme(?string $key = null, mixed $default = null): mixed
    {
        $service = app(ThemeService::class);

        if ($key === null) {
            return $service->getActive();
        }

        return $service->setting($key, $default);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * Get URL for a theme asset
     *
     * @param  string  $path  Path relative to theme's public directory
     * @return string Full asset URL
     *
     * @example
     * // Get theme logo
     * theme_asset('images/logo.svg') // "/themes/default/images/logo.svg"
     *
     * // Get theme stylesheet
     * theme_asset('css/custom.css')
     */
    function theme_asset(string $path): string
    {
        return app(ThemeService::class)->asset($path);
    }
}
