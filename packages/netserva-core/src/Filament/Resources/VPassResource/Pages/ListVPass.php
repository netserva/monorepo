<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\VPassResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Core\Filament\Resources\VPassResource;
use NetServa\Core\Filament\Resources\VPassResource\Tables\VPassTable;
use NetServa\Core\Models\VPass;

class ListVPass extends ListRecords
{
    protected static string $resource = VPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Credential')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::Large)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => VPassTable::getFormSchema())
                ->createAnother(false),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->tooltip('Backup all credentials to CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    $count = VPass::count();

                    Notification::make()
                        ->info()
                        ->title('Export via CLI')
                        ->body("Run: php artisan shpw --csv > backup.csv ({$count} credentials)")
                        ->persistent()
                        ->send();
                }),
            Action::make('backupReminder')
                ->label('Backup Info')
                ->tooltip('APP_KEY loss = credential loss')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->action(function () {
                    Notification::make()
                        ->warning()
                        ->title('Backup Reminder')
                        ->body('Credentials are encrypted with APP_KEY. If lost, credentials cannot be recovered. Export regularly: shpw --csv > backup.csv')
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
