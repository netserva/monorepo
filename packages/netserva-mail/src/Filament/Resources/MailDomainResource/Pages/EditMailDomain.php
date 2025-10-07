<?php

namespace NetServa\Mail\Filament\Resources\MailDomainResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Mail\Filament\Resources\MailDomainResource;

class EditMailDomain extends EditRecord
{
    protected static string $resource = MailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
