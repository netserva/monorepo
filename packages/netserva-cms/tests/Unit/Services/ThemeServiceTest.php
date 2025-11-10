<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;

describe('ThemeService', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);
    });

    it('creates default theme if none exists', function () {
        $theme = $this->service->getActive();

        expect($theme)->toBeInstanceOf(Theme::class)
            ->and($theme->name)->toBe('default')
            ->and($theme->is_active)->toBeTrue();
    });

    it('gets active theme from database', function () {
        $activeTheme = Theme::create([
            'name' => 'active-theme',
            'display_name' => 'Active Theme',
            'is_active' => true,
        ]);

        $theme = $this->service->getActive();

        expect($theme->name)->toBe('active-theme');
    });

    it('caches active theme', function () {
        Theme::create([
            'name' => 'cached-theme',
            'display_name' => 'Cached Theme',
            'is_active' => true,
        ]);

        // First call - should query database
        $theme1 = $this->service->getActive();

        // Second call - should use cache
        $theme2 = $this->service->getActive();

        expect($theme1)->toBe($theme2)
            ->and(Cache::has('cms.theme.active'))->toBeTrue();
    });
});

describe('Theme Activation', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);
    });

    it('activates a theme by name', function () {
        $theme1 = Theme::create([
            'name' => 'theme1',
            'display_name' => 'Theme 1',
            'is_active' => true,
        ]);

        $theme2 = Theme::create([
            'name' => 'theme2',
            'display_name' => 'Theme 2',
            'is_active' => false,
        ]);

        $this->service->activate('theme2');

        expect($theme1->fresh()->is_active)->toBeFalse()
            ->and($theme2->fresh()->is_active)->toBeTrue();
    });

    it('deactivates currently active theme when activating new one', function () {
        Theme::create([
            'name' => 'old-theme',
            'display_name' => 'Old Theme',
            'is_active' => true,
        ]);

        Theme::create([
            'name' => 'new-theme',
            'display_name' => 'New Theme',
            'is_active' => false,
        ]);

        $this->service->activate('new-theme');

        $activeThemes = Theme::where('is_active', true)->get();

        expect($activeThemes)->toHaveCount(1)
            ->and($activeThemes->first()->name)->toBe('new-theme');
    });

    it('throws exception when activating nonexistent theme', function () {
        expect(fn () => $this->service->activate('nonexistent'))
            ->toThrow(\RuntimeException::class);
    });

    it('clears cache when activating theme', function () {
        Theme::create([
            'name' => 'theme-a',
            'display_name' => 'Theme A',
            'is_active' => true,
        ]);

        Theme::create([
            'name' => 'theme-b',
            'display_name' => 'Theme B',
        ]);

        // Load into cache
        $this->service->getActive();
        expect(Cache::has('cms.theme.active'))->toBeTrue();

        // Activate new theme
        $this->service->activate('theme-b');

        // Cache should be cleared
        expect(Cache::has('cms.theme.active'))->toBeFalse();
    });
});

describe('Theme Settings', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);

        $this->theme = Theme::create([
            'name' => 'settings-theme',
            'display_name' => 'Settings Theme',
            'is_active' => true,
            'manifest' => [
                'settings' => [
                    'colors' => [
                        'primary' => '#000000',
                    ],
                ],
            ],
        ]);
    });

    it('gets theme setting value', function () {
        $this->theme->setSetting('colors.accent', '#FF0000');

        $value = $this->service->setting('colors.accent');

        expect($value)->toBe('#FF0000');
    });

    it('falls back to manifest for missing setting', function () {
        $value = $this->service->setting('colors.primary');

        expect($value)->toBe('#000000');
    });

    it('uses default when setting not found', function () {
        $value = $this->service->setting('nonexistent.key', 'default');

        expect($value)->toBe('default');
    });

    it('sets theme setting', function () {
        $this->service->setSetting('typography.font', 'Inter');

        expect($this->theme->fresh()->setting('typography.font'))->toBe('Inter');
    });

    it('clears CSS cache when setting changes', function () {
        // Generate CSS (creates cache)
        $this->service->generateCssVariables();
        expect(Cache::has("cms.theme.{$this->theme->id}.css"))->toBeTrue();

        // Change setting
        $this->service->setSetting('colors.primary', '#FF0000');

        // CSS cache should be cleared
        expect(Cache::has("cms.theme.{$this->theme->id}.css"))->toBeFalse();
    });
});

describe('CSS Variable Generation', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);

        $this->theme = Theme::create([
            'name' => 'css-theme',
            'display_name' => 'CSS Theme',
            'is_active' => true,
            'manifest' => [
                'settings' => [
                    'colors' => [
                        'palette' => [
                            ['slug' => 'primary', 'value' => '#DC2626'],
                            ['slug' => 'secondary', 'value' => '#1F2937'],
                        ],
                    ],
                    'typography' => [
                        'fonts' => [
                            'heading' => ['family' => 'Inter'],
                            'body' => ['family' => 'system-ui'],
                        ],
                        'sizes' => [
                            ['slug' => 'sm', 'value' => '0.875rem'],
                            ['slug' => 'base', 'value' => '1rem'],
                        ],
                    ],
                    'layout' => [
                        'contentWidth' => '800px',
                        'wideWidth' => '1200px',
                    ],
                ],
            ],
        ]);
    });

    it('generates CSS custom properties', function () {
        $css = $this->service->generateCssVariables();

        expect($css)->toContain(':root')
            ->and($css)->toContain('--color-primary: #DC2626')
            ->and($css)->toContain('--color-secondary: #1F2937')
            ->and($css)->toContain('--font-heading: Inter, sans-serif')
            ->and($css)->toContain('--font-body: system-ui, sans-serif')
            ->and($css)->toContain('--font-size-sm: 0.875rem')
            ->and($css)->toContain('--content-width: 800px')
            ->and($css)->toContain('--wide-width: 1200px');
    });

    it('uses database settings over manifest defaults', function () {
        $this->theme->setSetting('colors.primary', '#FF0000');

        $css = $this->service->generateCssVariables();

        expect($css)->toContain('--color-primary: #FF0000');
    });

    it('caches generated CSS', function () {
        $css1 = $this->service->generateCssVariables();
        $css2 = $this->service->generateCssVariables();

        expect($css1)->toBe($css2)
            ->and(Cache::has("cms.theme.{$this->theme->id}.css"))->toBeTrue();
    });
});

describe('Theme Discovery', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);
    });

    it('discovers themes from filesystem', function () {
        // Create test theme directory
        $themePath = resource_path('themes/discovered-theme');
        File::makeDirectory($themePath, 0755, true);

        $manifest = [
            'name' => 'discovered-theme',
            'display_name' => 'Discovered Theme',
            'version' => '1.0.0',
        ];

        File::put("{$themePath}/theme.json", json_encode($manifest));

        $count = $this->service->discover();

        expect($count)->toBeGreaterThan(0);

        $theme = Theme::where('name', 'discovered-theme')->first();

        expect($theme)->not->toBeNull()
            ->and($theme->display_name)->toBe('Discovered Theme')
            ->and($theme->version)->toBe('1.0.0');

        // Cleanup
        File::deleteDirectory($themePath);
    });

    it('updates existing theme during discovery', function () {
        // Create existing theme
        Theme::create([
            'name' => 'existing-theme',
            'display_name' => 'Old Name',
            'version' => '1.0.0',
        ]);

        // Create updated manifest
        $themePath = resource_path('themes/existing-theme');
        File::makeDirectory($themePath, 0755, true);

        $manifest = [
            'name' => 'existing-theme',
            'display_name' => 'New Name',
            'version' => '2.0.0',
        ];

        File::put("{$themePath}/theme.json", json_encode($manifest));

        $this->service->discover();

        $theme = Theme::where('name', 'existing-theme')->first();

        expect($theme->display_name)->toBe('New Name')
            ->and($theme->version)->toBe('2.0.0');

        // Cleanup
        File::deleteDirectory($themePath);
    });

    it('skips themes without theme.json', function () {
        $themePath = resource_path('themes/no-manifest-theme');
        File::makeDirectory($themePath, 0755, true);

        $count = $this->service->discover();

        $theme = Theme::where('name', 'no-manifest-theme')->first();

        expect($theme)->toBeNull();

        // Cleanup
        File::deleteDirectory($themePath);
    });

    it('skips themes with invalid JSON', function () {
        $themePath = resource_path('themes/invalid-theme');
        File::makeDirectory($themePath, 0755, true);

        File::put("{$themePath}/theme.json", '{invalid json}');

        $this->service->discover();

        $theme = Theme::where('name', 'invalid-theme')->first();

        expect($theme)->toBeNull();

        // Cleanup
        File::deleteDirectory($themePath);
    });
});

describe('Theme Assets', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);

        Theme::create([
            'name' => 'asset-theme',
            'display_name' => 'Asset Theme',
            'is_active' => true,
        ]);
    });

    it('generates theme asset URLs', function () {
        $url = $this->service->asset('images/logo.svg');

        expect($url)->toContain('themes/asset-theme/images/logo.svg');
    });
});

describe('Theme Utilities', function () {
    beforeEach(function () {
        $this->service = app(ThemeService::class);
    });

    it('checks if theme exists by name', function () {
        Theme::create([
            'name' => 'existing',
            'display_name' => 'Existing',
        ]);

        // Create theme directory
        $themePath = resource_path('themes/existing');
        File::makeDirectory($themePath, 0755, true);

        expect($this->service->exists('existing'))->toBeTrue()
            ->and($this->service->exists('nonexistent'))->toBeFalse();

        // Cleanup
        File::deleteDirectory($themePath);
    });

    it('gets all registered themes', function () {
        Theme::create(['name' => 'theme1', 'display_name' => 'Theme 1']);
        Theme::create(['name' => 'theme2', 'display_name' => 'Theme 2']);
        Theme::create(['name' => 'theme3', 'display_name' => 'Theme 3']);

        $themes = $this->service->all();

        expect($themes)->toHaveCount(3);
    });

    it('clears all theme cache', function () {
        $theme = Theme::create([
            'name' => 'cache-theme',
            'display_name' => 'Cache Theme',
            'is_active' => true,
        ]);

        // Load into cache
        $this->service->getActive();
        $this->service->generateCssVariables();

        expect(Cache::has('cms.theme.active'))->toBeTrue()
            ->and(Cache::has("cms.theme.{$theme->id}.css"))->toBeTrue();

        // Clear cache
        $this->service->clearCache();

        expect(Cache::has('cms.theme.active'))->toBeFalse()
            ->and(Cache::has("cms.theme.{$theme->id}.css"))->toBeFalse();
    });
});
