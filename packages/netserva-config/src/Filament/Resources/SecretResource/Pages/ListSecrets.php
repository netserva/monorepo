<?php

namespace NetServa\Config\Filament\Resources\SecretResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\SecretResource;

class ListSecrets extends ListRecords
{
    protected static string $resource = SecretResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
