<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\IpamResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Fleet\Filament\Resources\IpamResource;
use NetServa\Fleet\Filament\Resources\IpamResource\Schemas\NetworkForm;
use NetServa\Fleet\Services\IpamDiscoveryService;

class ListIpam extends ListRecords
{
    protected static string $resource = IpamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_from_fleet')
                ->label('Import from Fleet')
                ->icon(Heroicon::OutlinedCloudArrowDown)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Import from Fleet')
                ->modalDescription('This will discover IP addresses from VNodes and SSH Hosts, group them into /24 networks, and create corresponding network and address records.')
                ->modalSubmitActionLabel('Import')
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->action(function () {
                    $service = app(IpamDiscoveryService::class);
                    $stats = $service->discoverFromFleet();

                    $message = sprintf(
                        'Discovered %d new networks, %d new addresses, %d updated addresses',
                        $stats['networks_created'],
                        $stats['addresses_created'],
                        $stats['addresses_updated']
                    );

                    if ($stats['networks_created'] > 0 || $stats['addresses_created'] > 0) {
                        Notification::make()
                            ->title('Import Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('No New Data')
                            ->body('All networks and addresses already exist.')
                            ->info()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('New Network')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => NetworkForm::getFormSchema())
                ->createAnother(false),
        ];
    }
}
