<?php

namespace NetServa\Core\Filament\Resources\SystemServiceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SystemServiceResource;

class EditSystemService extends EditRecord
{
    protected static string $resource = SystemServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
