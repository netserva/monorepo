<?php

declare(strict_types=1);

namespace NetServa\Cms\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use NetServa\Cms\Models\Theme;

/**
 * Theme Service
 *
 * Handles theme activation, settings retrieval, and view path registration
 */
class ThemeService
{
    protected ?Theme $activeTheme = null;

    protected string $cacheKey = 'cms.theme.active';

    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Get the currently active theme (cached)
     */
    public function getActive(): Theme
    {
        if ($this->activeTheme) {
            return $this->activeTheme;
        }

        $this->activeTheme = Cache::remember(
            $this->cacheKey,
            $this->cacheTtl,
            fn () => Theme::active() ?? $this->getDefaultTheme()
        );

        return $this->activeTheme;
    }

    /**
     * Activate a theme by name
     */
    public function activate(string $themeName): bool
    {
        $theme = Theme::where('name', $themeName)->first();

        if (! $theme) {
            throw new \RuntimeException("Theme '{$themeName}' not found");
        }

        // Deactivate currently active theme
        Theme::where('is_active', true)->update(['is_active' => false]);

        // Activate new theme
        $theme->update(['is_active' => true]);

        // Register view paths for the new theme
        $this->registerViewPaths($theme);

        // Clear cache
        $this->clearCache();

        return true;
    }

    /**
     * Register theme view paths with Laravel's View system (including parent theme inheritance)
     */
    public function registerViewPaths(Theme $theme): void
    {
        $paths = $this->resolveViewPaths($theme);

        // Register each path with Laravel's View finder
        foreach ($paths as $path) {
            if (file_exists($path)) {
                View::addLocation($path);
            }
        }
    }

    /**
     * Resolve view paths with parent theme inheritance
     *
     * Returns array of paths in priority order:
     * 1. Child theme views (highest priority)
     * 2. Parent theme views
     * 3. CMS default views (fallback)
     */
    protected function resolveViewPaths(Theme $theme): array
    {
        $paths = [];

        // Child theme views (highest priority)
        if ($theme->exists()) {
            $paths[] = $theme->viewPath();
        }

        // Parent theme views (if exists)
        if ($theme->parent_theme) {
            $parent = $theme->parent;
            if ($parent && $parent->exists()) {
                $paths[] = $parent->viewPath();
            }
        }

        // CMS default views (fallback)
        $paths[] = __DIR__.'/../../resources/views';

        return $paths;
    }

    /**
     * Get a theme setting value
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->getActive()->setting($key, $default);
    }

    /**
     * Set a theme setting value
     */
    public function setSetting(string $key, mixed $value, string $category = 'general'): void
    {
        $this->getActive()->setSetting($key, $value, $category);

        // Clear CSS cache when settings change
        $this->clearCssCache();
    }

    /**
     * Generate CSS custom properties from theme settings
     */
    public function generateCssVariables(): string
    {
        $theme = $this->getActive();

        return Cache::remember(
            "cms.theme.{$theme->id}.css",
            $this->cacheTtl,
            fn () => $this->buildCssVariables($theme)
        );
    }

    /**
     * Build CSS custom properties string
     */
    protected function buildCssVariables(Theme $theme): string
    {
        $css = ":root {\n";

        // Colors
        $colors = $theme->colors();
        foreach ($colors as $color) {
            $slug = $color['slug'];
            $value = $theme->setting("colors.{$slug}", $color['value'] ?? '#000000');
            $css .= "    --color-{$slug}: {$value};\n";
        }

        // Typography
        $typography = $theme->typography();

        if (isset($typography['fonts']['heading']['family'])) {
            $family = $theme->setting('typography.fonts.heading.family', $typography['fonts']['heading']['family']);
            $css .= "    --font-heading: {$family}, sans-serif;\n";
        }

        if (isset($typography['fonts']['body']['family'])) {
            $family = $theme->setting('typography.fonts.body.family', $typography['fonts']['body']['family']);
            $css .= "    --font-body: {$family}, sans-serif;\n";
        }

        // Font sizes
        if (isset($typography['sizes'])) {
            foreach ($typography['sizes'] as $size) {
                $slug = $size['slug'];
                $value = $theme->setting("typography.sizes.{$slug}", $size['value']);
                $css .= "    --font-size-{$slug}: {$value};\n";
            }
        }

        // Layout
        $contentWidth = $theme->setting('layout.contentWidth', '800px');
        $wideWidth = $theme->setting('layout.wideWidth', '1200px');

        $css .= "    --content-width: {$contentWidth};\n";
        $css .= "    --wide-width: {$wideWidth};\n";

        $css .= "}\n";

        return $css;
    }

    /**
     * Get default theme (creates if doesn't exist)
     */
    protected function getDefaultTheme(): Theme
    {
        return Theme::firstOrCreate(
            ['name' => 'default'],
            [
                'display_name' => 'Default Theme',
                'description' => 'NetServa CMS default theme',
                'version' => '1.0.0',
                'is_active' => true,
                'manifest' => Theme::loadManifest('default') ?? $this->getDefaultManifest(),
            ]
        );
    }

    /**
     * Get default theme manifest if theme.json doesn't exist
     */
    protected function getDefaultManifest(): array
    {
        return [
            'name' => 'default',
            'display_name' => 'Default Theme',
            'description' => 'NetServa CMS default theme',
            'version' => '1.0.0',
            'settings' => [
                'colors' => [
                    'customizable' => true,
                    'palette' => [
                        ['name' => 'Primary', 'slug' => 'primary', 'value' => '#DC2626'],
                        ['name' => 'Secondary', 'slug' => 'secondary', 'value' => '#1F2937'],
                        ['name' => 'Accent', 'slug' => 'accent', 'value' => '#3B82F6'],
                    ],
                ],
                'typography' => [
                    'customizable' => true,
                    'fonts' => [
                        'heading' => [
                            'family' => 'Inter',
                            'provider' => 'bunny',
                        ],
                        'body' => [
                            'family' => 'system-ui',
                            'provider' => 'system',
                        ],
                    ],
                    'sizes' => [
                        ['name' => 'Small', 'slug' => 'sm', 'value' => '0.875rem'],
                        ['name' => 'Base', 'slug' => 'base', 'value' => '1rem'],
                        ['name' => 'Large', 'slug' => 'lg', 'value' => '1.125rem'],
                        ['name' => 'XL', 'slug' => 'xl', 'value' => '1.25rem'],
                    ],
                ],
                'layout' => [
                    'contentWidth' => '800px',
                    'wideWidth' => '1200px',
                ],
            ],
        ];
    }

    /**
     * Clear all theme cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->clearCssCache();
        $this->activeTheme = null;
    }

    /**
     * Clear CSS cache for current theme
     */
    protected function clearCssCache(): void
    {
        if ($this->activeTheme) {
            Cache::forget("cms.theme.{$this->activeTheme->id}.css");
        }
    }

    /**
     * Get theme asset URL
     */
    public function asset(string $path): string
    {
        $theme = $this->getActive();

        return asset("themes/{$theme->name}/{$path}");
    }

    /**
     * Check if a theme exists
     */
    public function exists(string $themeName): bool
    {
        $theme = Theme::where('name', $themeName)->first();

        return $theme && $theme->exists();
    }

    /**
     * Get all registered themes
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Theme::orderBy('name')->get();
    }

    /**
     * Discover and register themes from filesystem
     */
    public function discover(): int
    {
        $themePath = resource_path('themes');
        $registered = 0;

        if (! is_dir($themePath)) {
            return 0;
        }

        foreach (glob("{$themePath}/*", GLOB_ONLYDIR) as $dir) {
            $themeName = basename($dir);
            $manifestPath = "{$dir}/theme.json";

            if (! file_exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = Theme::loadManifest($themeName);

                Theme::updateOrCreate(
                    ['name' => $themeName],
                    [
                        'display_name' => $manifest['display_name'] ?? $themeName,
                        'description' => $manifest['description'] ?? '',
                        'version' => $manifest['version'] ?? '1.0.0',
                        'author' => $manifest['author'] ?? '',
                        'parent_theme' => $manifest['parent'] ?? null,
                        'manifest' => $manifest,
                    ]
                );

                $registered++;
            } catch (\Exception $e) {
                // Skip themes with invalid manifests
                continue;
            }
        }

        return $registered;
    }
}
