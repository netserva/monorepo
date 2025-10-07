<?php

namespace NetServa\Mail\Filament\Resources\MailLogResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Mail\Filament\Resources\MailLogResource;

class EditMailLog extends EditRecord
{
    protected static string $resource = MailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
