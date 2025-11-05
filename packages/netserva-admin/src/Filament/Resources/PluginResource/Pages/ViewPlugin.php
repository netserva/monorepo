<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\PluginResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Admin\Filament\Resources\PluginResource;

class ViewPlugin extends ViewRecord
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle')
                ->label(fn () => $this->record->is_enabled ? 'Disable Plugin' : 'Enable Plugin')
                ->icon(fn () => $this->record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn () => $this->record->is_enabled ? 'danger' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['is_enabled' => ! $this->record->is_enabled]);

                    \Filament\Notifications\Notification::make()
                        ->title('Plugin '.($this->record->is_enabled ? 'enabled' : 'disabled'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
