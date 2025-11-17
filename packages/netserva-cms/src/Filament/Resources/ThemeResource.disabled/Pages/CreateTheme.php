<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\ThemeResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\File;
use NetServa\Cms\Filament\Resources\ThemeResource;
use NetServa\Cms\Models\Theme;

/**
 * Create Theme Page
 *
 * Automatically scaffolds new themes by copying the default theme structure
 */
class CreateTheme extends CreateRecord
{
    protected static string $resource = ThemeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Load manifest from filesystem if it exists
        if (isset($data['name'])) {
            $manifest = Theme::loadManifest($data['name']);
            if ($manifest) {
                $data['manifest'] = $manifest;

                // Auto-fill from manifest if not provided
                $data['display_name'] = $data['display_name'] ?? $manifest['display_name'] ?? $data['name'];
                $data['description'] = $data['description'] ?? $manifest['description'] ?? '';
                $data['version'] = $data['version'] ?? $manifest['version'] ?? '1.0.0';
                $data['author'] = $data['author'] ?? $manifest['author'] ?? '';
                $data['parent_theme'] = $data['parent_theme'] ?? $manifest['parent'] ?? null;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $theme = $this->record;
        $themePath = resource_path("themes/{$theme->name}");

        // If theme directory doesn't exist, scaffold from default theme
        if (! File::exists($themePath)) {
            $this->scaffoldThemeFromDefault($theme);
        }
    }

    /**
     * Scaffold new theme by copying default theme structure
     */
    protected function scaffoldThemeFromDefault(Theme $theme): void
    {
        $defaultPath = resource_path('themes/default');
        $themePath = resource_path("themes/{$theme->name}");

        // Check if default theme exists
        if (! File::exists($defaultPath)) {
            Notification::make()
                ->warning()
                ->title('Default theme not found')
                ->body('Could not scaffold theme - default theme template is missing.')
                ->send();

            return;
        }

        try {
            // Copy default theme structure
            File::copyDirectory($defaultPath, $themePath);

            // Update theme.json with new theme details
            $manifestPath = "{$themePath}/theme.json";
            if (File::exists($manifestPath)) {
                $manifest = json_decode(File::get($manifestPath), true);

                // Update manifest with new theme data
                $manifest['name'] = $theme->name;
                $manifest['display_name'] = $theme->display_name;
                $manifest['description'] = $theme->description ?? 'A custom NetServa theme';
                $manifest['version'] = $theme->version ?? '1.0.0';
                $manifest['author'] = $theme->author ?? '';
                $manifest['parent'] = $theme->parent_theme;

                // Write updated manifest
                File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                // Reload manifest into theme record
                $theme->update(['manifest' => $manifest]);
            }

            Notification::make()
                ->success()
                ->title('Theme Scaffolded')
                ->body("Created theme structure in resources/themes/{$theme->name}/ based on default theme.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Scaffolding Failed')
                ->body("Could not create theme files: {$e->getMessage()}")
                ->send();
        }
    }
}
