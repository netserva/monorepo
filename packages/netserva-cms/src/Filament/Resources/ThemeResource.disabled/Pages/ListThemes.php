<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\ThemeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cms\Filament\Resources\ThemeResource;

/**
 * List Themes Page
 *
 * Theme discovery is manual-only via the "Discover Themes" toolbar button.
 * Auto-polling is disabled to prevent excessive logging and resource usage.
 */
class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    /**
     * Disable auto-polling to prevent excessive plugin discovery logging
     * Theme discovery is manual-only via toolbar button
     */
    protected static ?string $pollingInterval = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->label('Create Theme'),
        ];
    }
}
