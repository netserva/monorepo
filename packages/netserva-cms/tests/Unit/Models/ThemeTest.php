<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Models\ThemeSetting;

describe('Theme Model', function () {
    it('can create a theme', function () {
        $theme = Theme::create([
            'name' => 'test-theme',
            'display_name' => 'Test Theme',
            'version' => '1.0.0',
        ]);

        expect($theme)->toBeInstanceOf(Theme::class)
            ->and($theme->name)->toBe('test-theme')
            ->and($theme->display_name)->toBe('Test Theme')
            ->and($theme->version)->toBe('1.0.0');
    });

    it('casts is_active to boolean', function () {
        $theme = Theme::create([
            'name' => 'active-theme',
            'display_name' => 'Active Theme',
            'is_active' => true,
        ]);

        expect($theme->is_active)->toBeTrue()
            ->and($theme->is_active)->toBeBool();
    });

    it('casts manifest to array', function () {
        $manifest = [
            'settings' => [
                'colors' => ['primary' => '#DC2626'],
            ],
        ];

        $theme = Theme::create([
            'name' => 'manifest-theme',
            'display_name' => 'Manifest Theme',
            'manifest' => $manifest,
        ]);

        expect($theme->manifest)->toBeArray()
            ->and($theme->manifest)->toBe($manifest);
    });

    it('has settings relationship', function () {
        $theme = Theme::create([
            'name' => 'settings-theme',
            'display_name' => 'Settings Theme',
        ]);

        ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'colors.primary',
            'value' => '#FF0000',
            'type' => 'color',
        ]);

        expect($theme->settings)->toHaveCount(1)
            ->and($theme->settings->first())->toBeInstanceOf(ThemeSetting::class);
    });

    it('has parent relationship', function () {
        $parent = Theme::create([
            'name' => 'parent',
            'display_name' => 'Parent Theme',
        ]);

        $child = Theme::create([
            'name' => 'child',
            'display_name' => 'Child Theme',
            'parent_theme' => 'parent',
        ]);

        expect($child->parent)->toBeInstanceOf(Theme::class)
            ->and($child->parent->name)->toBe('parent');
    });

    it('has children relationship', function () {
        $parent = Theme::create([
            'name' => 'parent-theme',
            'display_name' => 'Parent Theme',
        ]);

        Theme::create([
            'name' => 'child1',
            'display_name' => 'Child 1',
            'parent_theme' => 'parent-theme',
        ]);

        Theme::create([
            'name' => 'child2',
            'display_name' => 'Child 2',
            'parent_theme' => 'parent-theme',
        ]);

        expect($parent->children)->toHaveCount(2);
    });

    it('gets active theme', function () {
        Theme::create([
            'name' => 'inactive1',
            'display_name' => 'Inactive 1',
            'is_active' => false,
        ]);

        $active = Theme::create([
            'name' => 'active',
            'display_name' => 'Active Theme',
            'is_active' => true,
        ]);

        Theme::create([
            'name' => 'inactive2',
            'display_name' => 'Inactive 2',
            'is_active' => false,
        ]);

        expect(Theme::active())->toBeInstanceOf(Theme::class)
            ->and(Theme::active()->name)->toBe('active');
    });

    it('returns null when no active theme', function () {
        expect(Theme::active())->toBeNull();
    });

    it('generates correct theme paths', function () {
        $theme = Theme::create([
            'name' => 'path-theme',
            'display_name' => 'Path Theme',
        ]);

        expect($theme->path())->toBe(resource_path('themes/path-theme'))
            ->and($theme->viewPath())->toBe(resource_path('themes/path-theme/resources/views'))
            ->and($theme->assetsPath())->toBe(resource_path('themes/path-theme/resources'));
    });
});

describe('Theme Settings', function () {
    it('gets setting from database', function () {
        $theme = Theme::create([
            'name' => 'db-settings-theme',
            'display_name' => 'DB Settings Theme',
        ]);

        $theme->setSetting('colors.primary', '#FF0000');

        expect($theme->setting('colors.primary'))->toBe('#FF0000');
    });

    it('falls back to manifest for missing setting', function () {
        $theme = Theme::create([
            'name' => 'manifest-settings-theme',
            'display_name' => 'Manifest Settings Theme',
            'manifest' => [
                'settings' => [
                    'colors' => [
                        'primary' => '#000000',
                    ],
                ],
            ],
        ]);

        expect($theme->setting('colors.primary'))->toBe('#000000');
    });

    it('uses provided default when setting not found', function () {
        $theme = Theme::create([
            'name' => 'default-theme',
            'display_name' => 'Default Theme',
        ]);

        expect($theme->setting('nonexistent.key', 'default-value'))->toBe('default-value');
    });

    it('sets setting with automatic category', function () {
        $theme = Theme::create([
            'name' => 'set-theme',
            'display_name' => 'Set Theme',
        ]);

        $setting = $theme->setSetting('colors.accent', '#3B82F6', 'colors');

        expect($setting)->toBeInstanceOf(ThemeSetting::class)
            ->and($setting->key)->toBe('colors.accent')
            ->and($setting->category)->toBe('colors')
            ->and($setting->getTypedValue())->toBe('#3B82F6');
    });

    it('updates existing setting', function () {
        $theme = Theme::create([
            'name' => 'update-theme',
            'display_name' => 'Update Theme',
        ]);

        $theme->setSetting('colors.primary', '#FF0000');
        $theme->setSetting('colors.primary', '#00FF00');

        expect($theme->setting('colors.primary'))->toBe('#00FF00')
            ->and($theme->settings()->count())->toBe(1); // Should not create duplicate
    });
});

describe('Theme Manifest', function () {
    it('loads manifest from theme.json file', function () {
        $themePath = resource_path('themes/test-manifest');
        File::makeDirectory($themePath, 0755, true);

        $manifest = [
            'name' => 'test-manifest',
            'display_name' => 'Test Manifest Theme',
            'version' => '2.0.0',
        ];

        File::put("{$themePath}/theme.json", json_encode($manifest));

        $loaded = Theme::loadManifest('test-manifest');

        expect($loaded)->toBeArray()
            ->and($loaded['name'])->toBe('test-manifest')
            ->and($loaded['version'])->toBe('2.0.0');

        // Cleanup
        File::deleteDirectory($themePath);
    });

    it('returns null when theme.json does not exist', function () {
        expect(Theme::loadManifest('nonexistent-theme'))->toBeNull();
    });

    it('throws exception for invalid JSON', function () {
        $themePath = resource_path('themes/invalid-json');
        File::makeDirectory($themePath, 0755, true);

        File::put("{$themePath}/theme.json", '{invalid json}');

        expect(fn () => Theme::loadManifest('invalid-json'))
            ->toThrow(\RuntimeException::class);

        // Cleanup
        File::deleteDirectory($themePath);
    });
});

describe('Theme Helpers', function () {
    it('gets colors from manifest', function () {
        $theme = Theme::create([
            'name' => 'colors-theme',
            'display_name' => 'Colors Theme',
            'manifest' => [
                'settings' => [
                    'colors' => [
                        'palette' => [
                            ['name' => 'Primary', 'slug' => 'primary', 'value' => '#DC2626'],
                            ['name' => 'Secondary', 'slug' => 'secondary', 'value' => '#1F2937'],
                        ],
                    ],
                ],
            ],
        ]);

        $colors = $theme->colors();

        expect($colors)->toBeArray()
            ->and($colors)->toHaveCount(2)
            ->and($colors[0]['slug'])->toBe('primary');
    });

    it('gets typography from manifest', function () {
        $theme = Theme::create([
            'name' => 'typography-theme',
            'display_name' => 'Typography Theme',
            'manifest' => [
                'settings' => [
                    'typography' => [
                        'fonts' => [
                            'heading' => ['family' => 'Inter'],
                        ],
                    ],
                ],
            ],
        ]);

        $typography = $theme->typography();

        expect($typography)->toBeArray()
            ->and($typography['fonts']['heading']['family'])->toBe('Inter');
    });

    it('gets templates from manifest', function () {
        $theme = Theme::create([
            'name' => 'templates-theme',
            'display_name' => 'Templates Theme',
            'manifest' => [
                'templates' => [
                    'page' => [
                        ['name' => 'default', 'label' => 'Default'],
                        ['name' => 'landing', 'label' => 'Landing Page'],
                    ],
                ],
            ],
        ]);

        $templates = $theme->templates('page');

        expect($templates)->toBeArray()
            ->and($templates)->toHaveCount(2)
            ->and($templates[0]['name'])->toBe('default');
    });
});
