<?php

namespace NetServa\Config\Filament\Resources\SecretAccessResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\SecretAccessResource;

class EditSecretAccess extends EditRecord
{
    protected static string $resource = SecretAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
