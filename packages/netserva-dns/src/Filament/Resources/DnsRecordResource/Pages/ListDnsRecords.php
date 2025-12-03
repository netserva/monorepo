<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Dns\Filament\Resources\DnsRecordResource;
use NetServa\Dns\Filament\Resources\DnsZoneResource;
use NetServa\Dns\Models\DnsZone;

class ListDnsRecords extends ListRecords
{
    protected static string $resource = DnsRecordResource::class;

    public function getTitle(): string
    {
        $zoneId = request()->input('tableFilters.dns_zone_id.value');
        if ($zoneId) {
            $zone = DnsZone::find($zoneId);
            if ($zone) {
                return 'Records: '.rtrim($zone->name, '.');
            }
        }

        return 'Dns Records';
    }

    protected function getHeaderActions(): array
    {
        $zoneId = request()->input('tableFilters.dns_zone_id.value');
        $zone = $zoneId ? DnsZone::find($zoneId) : null;

        return [
            Action::make('back_to_zones')
                ->label('Zones')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => $zone?->dns_provider_id
                    ? DnsZoneResource::getUrl('index', [
                        'tableFilters' => [
                            'dns_provider_id' => ['value' => $zone->dns_provider_id],
                        ],
                    ])
                    : DnsZoneResource::getUrl()),

            CreateAction::make()
                ->label('New Record')
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DnsRecordResource::getFormSchema()),
        ];
    }
}
