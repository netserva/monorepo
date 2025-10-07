<?php

namespace NetServa\Mail\Filament\Resources\MailAliasResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Mail\Filament\Resources\MailAliasResource;

class EditMailAlias extends EditRecord
{
    protected static string $resource = MailAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
