<?php

namespace NetServa\Core\Filament\Resources\VHostResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\VHostResource;

class EditVHost extends EditRecord
{
    protected static string $resource = VHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
