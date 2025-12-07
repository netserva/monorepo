<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;

beforeEach(function () {
    // Ensure we have an active theme
    $this->theme = Theme::factory()->create([
        'name' => 'test-theme',
        'display_name' => 'Test Theme',
        'is_active' => true,
        'manifest' => [
            'settings' => [
                'color' => [
                    [
                        'slug' => 'primary',
                        'name' => 'Primary Color',
                        'value' => '#3B82F6',
                    ],
                    [
                        'slug' => 'secondary',
                        'name' => 'Secondary Color',
                        'value' => '#10B981',
                    ],
                ],
                'typography' => [
                    'fonts' => [
                        'heading' => ['family' => 'Inter', 'provider' => 'google'],
                        'body' => ['family' => 'system-ui', 'provider' => 'system'],
                    ],
                ],
                'layout' => [
                    'contentWidth' => '800px',
                    'wideWidth' => '1200px',
                ],
            ],
        ],
    ]);

    // Authenticate as admin
    $this->actingAs(\App\Models\User::factory()->create());
});

/**
 * ThemeResource is disabled - these Livewire tests are skipped
 *
 * @see src/Filament/Resources/ThemeResource.php.disabled
 */
it('can open the edit theme modal action', function () {
    //
})->skip('ThemeResource is disabled');

it('can save theme settings via modal action', function () {
    //
})->skip('ThemeResource is disabled');

/**
 * ThemeService tests - these test the service layer directly (not disabled)
 */
it('can save theme settings via model', function () {
    $theme = app(ThemeService::class)->getActive();

    $theme->setSetting('colors.primary', '#FF0000', 'colors');
    $theme->setSetting('colors.secondary', '#00FF00', 'colors');

    expect($theme->setting('colors.primary'))->toBe('#FF0000');
    expect($theme->setting('colors.secondary'))->toBe('#00FF00');
});

it('saves settings with correct types', function () {
    $theme = app(ThemeService::class)->getActive();

    $theme->setSetting('colors.primary', '#FF5733', 'colors');
    $theme->setSetting('layout.contentWidth', '900px', 'layout');

    $colorSetting = $theme->settings()->where('key', 'colors.primary')->first();
    $layoutSetting = $theme->settings()->where('key', 'layout.contentWidth')->first();

    expect($colorSetting)->not->toBeNull();
    expect($layoutSetting)->not->toBeNull();
    expect($colorSetting->type)->toBe('color');
    expect($layoutSetting->type)->toBe('string');
});

it('clears cache when ThemeService is called', function () {
    $service = app(ThemeService::class);

    // First generate cache by calling these methods
    $service->getActive();
    $service->generateCssVariables();

    // Verify cache exists
    expect(Cache::has('cms.theme.active'))->toBeTrue();

    $service->clearCache();

    // Cache should be cleared
    expect(Cache::has('cms.theme.active'))->toBeFalse();
});

it('can reset settings to defaults', function () {
    $theme = app(ThemeService::class)->getActive();

    // Set custom settings
    $theme->setSetting('colors.primary', '#FF0000', 'colors');
    $theme->setSetting('colors.secondary', '#00FF00', 'colors');

    expect($theme->settings()->count())->toBe(2);
    expect($theme->setting('colors.primary'))->toBe('#FF0000');

    // Delete all settings (like the reset action does)
    $theme->settings()->delete();

    // Refresh to clear cached settings relationship
    $theme->refresh();

    // All settings should be deleted
    expect($theme->settings()->count())->toBe(0);

    // After deletion, setting should be null (no fallback in current implementation)
    expect($theme->setting('colors.primary'))->toBeNull();
});

it('generates CSS variables from theme', function () {
    $service = app(ThemeService::class);
    $css = $service->generateCssVariables();

    expect($css)->toContain(':root {');
    expect($css)->toContain('--font-heading');
    expect($css)->toContain('--font-body');
    expect($css)->toContain('--content-width');
    expect($css)->toContain('--wide-width');
});

it('caches generated CSS variables', function () {
    $service = app(ThemeService::class);

    // Clear any existing cache first
    $service->clearCache();

    // First call generates and caches
    $css1 = $service->generateCssVariables();

    // Second call should return same version
    $css2 = $service->generateCssVariables();

    expect($css1)->toBe($css2);
});

it('handles parent theme inheritance', function () {
    $parentTheme = Theme::factory()->create([
        'name' => 'parent-theme',
        'is_active' => false,
        'manifest' => [
            'settings' => [
                'color' => [
                    ['slug' => 'primary', 'name' => 'Primary', 'value' => '#PARENT'],
                ],
            ],
        ],
    ]);

    $childTheme = Theme::factory()->create([
        'name' => 'child-theme',
        'parent_theme' => 'parent-theme',
        'is_active' => false,
    ]);

    expect($childTheme->parent)->not->toBeNull();
    expect($childTheme->parent->name)->toBe('parent-theme');
});
