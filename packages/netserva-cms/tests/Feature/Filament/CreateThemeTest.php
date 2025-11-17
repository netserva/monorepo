<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use NetServa\Cms\Filament\Resources\ThemeResource\Pages\CreateTheme;
use NetServa\Cms\Models\Theme;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Ensure default theme exists for scaffolding
    $defaultPath = resource_path('themes/default');
    if (! File::exists($defaultPath)) {
        File::makeDirectory($defaultPath, 0755, true);
        File::put("{$defaultPath}/theme.json", json_encode([
            'name' => 'default',
            'display_name' => 'Default Theme',
            'version' => '1.0.0',
            'settings' => [
                'colors' => [
                    'palette' => [
                        ['name' => 'Primary', 'slug' => 'primary', 'value' => '#DC2626'],
                    ],
                ],
                'typography' => [
                    'fonts' => [
                        'heading' => ['family' => 'Inter'],
                    ],
                ],
                'layout' => [
                    'contentWidth' => '800px',
                ],
            ],
        ], JSON_PRETTY_PRINT));
    }
});

afterEach(function () {
    // Clean up created test theme
    $testThemePath = resource_path('themes/test-theme');
    if (File::exists($testThemePath)) {
        File::deleteDirectory($testThemePath);
    }
});

describe('Theme Creation', function () {
    it('can create a theme via Filament', function () {
        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'test-theme',
                'display_name' => 'Test Theme',
                'description' => 'A test theme',
                'version' => '1.0.0',
                'author' => 'Test Author',
            ])
            ->call('create')
            ->assertNotified();

        assertDatabaseHas(Theme::class, [
            'name' => 'test-theme',
            'display_name' => 'Test Theme',
        ]);
    });

    it('scaffolds theme directory from default when creating new theme', function () {
        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'test-theme',
                'display_name' => 'Test Theme',
                'version' => '1.0.0',
            ])
            ->call('create')
            ->assertNotified('Theme Scaffolded');

        $testThemePath = resource_path('themes/test-theme');

        expect(File::exists($testThemePath))->toBeTrue()
            ->and(File::exists("{$testThemePath}/theme.json"))->toBeTrue();
    });

    it('updates scaffolded theme.json with new theme details', function () {
        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'test-theme',
                'display_name' => 'Test Theme',
                'description' => 'A custom test theme',
                'version' => '2.0.0',
                'author' => 'Test Author',
            ])
            ->call('create')
            ->assertNotified();

        $manifestPath = resource_path('themes/test-theme/theme.json');
        expect(File::exists($manifestPath))->toBeTrue();

        $manifest = json_decode(File::get($manifestPath), true);

        expect($manifest['name'])->toBe('test-theme')
            ->and($manifest['display_name'])->toBe('Test Theme')
            ->and($manifest['description'])->toBe('A custom test theme')
            ->and($manifest['version'])->toBe('2.0.0')
            ->and($manifest['author'])->toBe('Test Author');
    });

    it('loads manifest into theme record after scaffolding', function () {
        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'test-theme',
                'display_name' => 'Test Theme',
            ])
            ->call('create');

        $theme = Theme::where('name', 'test-theme')->first();

        expect($theme->manifest)->not->toBeNull()
            ->and($theme->manifest['name'])->toBe('test-theme')
            ->and($theme->manifest['settings']['colors']['palette'])->toBeArray();
    });

    it('shows warning when default theme is missing', function () {
        // Remove default theme temporarily
        $defaultPath = resource_path('themes/default');
        $tempPath = resource_path('themes/default.bak');

        if (File::exists($defaultPath)) {
            File::move($defaultPath, $tempPath);
        }

        try {
            Livewire::test(CreateTheme::class)
                ->fillForm([
                    'name' => 'test-theme',
                    'display_name' => 'Test Theme',
                ])
                ->call('create')
                ->assertNotified('Default theme not found');

            // Theme created in DB but no filesystem scaffolding
            assertDatabaseHas(Theme::class, ['name' => 'test-theme']);
            expect(File::exists(resource_path('themes/test-theme')))->toBeFalse();
        } finally {
            // Restore default theme
            if (File::exists($tempPath)) {
                File::move($tempPath, $defaultPath);
            }
        }
    });
});
