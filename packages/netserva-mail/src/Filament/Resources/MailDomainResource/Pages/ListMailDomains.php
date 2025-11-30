<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources\MailDomainResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Mail\Filament\Resources\MailDomainResource;

class ListMailDomains extends ListRecords
{
    protected static string $resource = MailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::ExtraLarge)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => MailDomainResource::getFormSchema()),
        ];
    }
}
