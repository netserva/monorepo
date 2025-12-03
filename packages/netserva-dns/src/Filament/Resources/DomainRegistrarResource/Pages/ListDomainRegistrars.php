<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

class ListDomainRegistrars extends ListRecords
{
    protected static string $resource = DomainRegistrarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_domains')
                ->label('Domains')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(DomainRegistrationResource::getUrl()),

            CreateAction::make()
                ->label('New Registrar')
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false)
                ->modalWidth(Width::ExtraLarge)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DomainRegistrarResource::getFormSchema()),
        ];
    }
}
