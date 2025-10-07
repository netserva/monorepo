<?php

namespace NetServa\Mail\Filament\Resources\MailServerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Mail\Filament\Resources\MailServerResource;

class EditMailServer extends EditRecord
{
    protected static string $resource = MailServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
