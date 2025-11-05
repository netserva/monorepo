<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\AuditLogResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Admin\Filament\Resources\AuditLogResource;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clear_old_logs')
                ->label('Clear Old Logs')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear Old Audit Logs')
                ->modalDescription('This will permanently delete audit logs older than 90 days. This action cannot be undone.')
                ->modalSubmitActionLabel('Delete Old Logs')
                ->action(function () {
                    $deleted = \NetServa\Core\Models\AuditLog::where('created_at', '<', now()->subDays(90))->delete();

                    \Filament\Notifications\Notification::make()
                        ->title("Deleted {$deleted} old audit logs")
                        ->success()
                        ->send();
                })
                ->hidden(fn () => ! auth()->user()?->can('delete', \NetServa\Core\Models\AuditLog::class)),
        ];
    }
}
