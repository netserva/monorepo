<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Dns\Filament\Resources\DnsProviderResource;
use NetServa\Dns\Filament\Resources\DnsZoneResource;
use NetServa\Dns\Models\DnsProvider;

class ListDnsZones extends ListRecords
{
    protected static string $resource = DnsZoneResource::class;

    public function getTitle(): string
    {
        $providerId = request()->input('tableFilters.dns_provider_id.value');
        if ($providerId) {
            $provider = DnsProvider::find($providerId);
            if ($provider) {
                return "Zones: {$provider->name}";
            }
        }

        return 'Dns Zones';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_providers')
                ->label('Providers')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(DnsProviderResource::getUrl()),

            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DnsZoneResource::getFormSchema()),
        ];
    }
}
