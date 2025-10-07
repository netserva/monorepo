<?php

namespace NetServa\Config\Filament\Resources\ConfigProfileResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\ConfigProfileResource;

class EditConfigProfile extends EditRecord
{
    protected static string $resource = ConfigProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
