<?php

namespace NetServa\Config\Filament\Resources\DatabaseCredentialResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource;

class ListDatabaseCredentials extends ListRecords
{
    protected static string $resource = DatabaseCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
