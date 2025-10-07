<?php

namespace NetServa\Web\Filament\Resources\WebApplicationResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Web\Filament\Resources\WebApplicationResource;

class EditWebApplication extends EditRecord
{
    protected static string $resource = WebApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
