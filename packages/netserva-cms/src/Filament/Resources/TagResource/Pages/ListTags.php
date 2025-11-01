<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\TagResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cms\Filament\Resources\TagResource;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
