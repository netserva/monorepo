<?php

namespace NetServa\Web\Filament\Resources\WebServerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Web\Filament\Resources\WebServerResource;

class EditWebServer extends EditRecord
{
    protected static string $resource = WebServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
