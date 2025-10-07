<?php

namespace NetServa\Mail\Filament\Resources\MailAliasResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Mail\Filament\Resources\MailAliasResource;

class ListMailAliases extends ListRecords
{
    protected static string $resource = MailAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
