<?php

namespace NetServa\Config\Filament\Resources\DatabaseCredentialResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource;

class EditDatabaseCredential extends EditRecord
{
    protected static string $resource = DatabaseCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
