<?php

namespace NetServa\Mail\Filament\Resources\MailServerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Mail\Filament\Resources\MailServerResource;

class ListMailServers extends ListRecords
{
    protected static string $resource = MailServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => MailServerResource::getFormSchema()),
        ];
    }
}
