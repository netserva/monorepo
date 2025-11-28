<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\PluginResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\PluginResource;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('discover')
                ->label('Discover Plugins')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->action(function () {
                    // Trigger plugin discovery
                    \Artisan::call('plugin:discover');

                    \Filament\Notifications\Notification::make()
                        ->title('Plugin discovery completed')
                        ->success()
                        ->send();
                }),
        ];
    }
}
