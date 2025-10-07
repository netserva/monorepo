<?php

namespace NetServa\Config\Filament\Resources\SecretResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\SecretResource;

class EditSecret extends EditRecord
{
    protected static string $resource = SecretResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
