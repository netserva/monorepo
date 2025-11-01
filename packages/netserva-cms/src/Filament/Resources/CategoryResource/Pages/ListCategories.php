<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\CategoryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cms\Filament\Resources\CategoryResource;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
