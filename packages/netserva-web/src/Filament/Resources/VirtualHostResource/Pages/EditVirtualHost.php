<?php

namespace NetServa\Web\Filament\Resources\VirtualHostResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Web\Filament\Resources\VirtualHostResource;

class EditVirtualHost extends EditRecord
{
    protected static string $resource = VirtualHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
