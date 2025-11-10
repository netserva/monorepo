<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\ThemeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cms\Filament\Resources\ThemeResource;

/**
 * List Themes Page
 */
class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Theme'),
        ];
    }
}
