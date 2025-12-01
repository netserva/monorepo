<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

class ListDomainRegistrations extends ListRecords
{
    protected static string $resource = DomainRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DomainRegistrationResource::getFormSchema()),
        ];
    }
}
