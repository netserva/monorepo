<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\ThemeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\ThemeResource;
use NetServa\Cms\Models\Theme;

/**
 * Create Theme Page
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
}
