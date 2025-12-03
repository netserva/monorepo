<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Core\Filament\Resources\SshHostResource;
use NetServa\Core\Filament\Resources\SshHostResource\Schemas\SshHostForm;
use NetServa\Core\Services\SshHostSyncService;

class ListSshHosts extends ListRecords
{
    protected static string $resource = SshHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New SSH Host')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => SshHostForm::getFormSchema())
                ->createAnother(false),
            Action::make('importFromFilesystem')
                ->label('Import')
                ->tooltip('Import from ~/.ssh/hosts/')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    $service = app(SshHostSyncService::class);
                    $results = $service->importFromFilesystem();

                    Notification::make()
                        ->success()
                        ->title('Import Complete')
                        ->body("Imported: {$results['imported']}, Skipped: {$results['skipped']}")
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Import SSH Hosts')
                ->modalDescription('Import existing SSH host configurations from ~/.ssh/hosts/ into the database.'),
        ];
    }
}
