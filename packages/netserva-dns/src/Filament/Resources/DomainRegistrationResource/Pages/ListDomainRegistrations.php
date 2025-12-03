<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

class ListDomainRegistrations extends ListRecords
{
    protected static string $resource = DomainRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrars')
                ->label('Registrars')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->color('gray')
                ->url(DomainRegistrarResource::getUrl()),

            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::ExtraLarge)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DomainRegistrationResource::getFormSchema()),
        ];
    }
}
