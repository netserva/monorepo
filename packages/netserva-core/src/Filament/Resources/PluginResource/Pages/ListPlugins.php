<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\PluginResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\PluginResource;
use NetServa\Core\Foundation\PluginRegistry;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncAll')
                ->label('Sync All')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $registry = app(PluginRegistry::class);
                    $results = $registry->syncAllPlugins();

                    Notification::make()
                        ->success()
                        ->title('Plugins Synced')
                        ->body("Synced: {$results['synced']}, Failed: {$results['failed']}")
                        ->send();
                }),

            Actions\Action::make('discover')
                ->label('Discover')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function () {
                    \Artisan::call('plugin:discover');

                    Notification::make()
                        ->title('Plugin discovery completed')
                        ->success()
                        ->send();
                }),
        ];
    }
}
