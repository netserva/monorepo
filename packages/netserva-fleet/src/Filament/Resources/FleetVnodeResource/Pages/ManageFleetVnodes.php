<?php

namespace NetServa\Fleet\Filament\Resources\FleetVnodeResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;

class ManageFleetVnodes extends ManageRecords
{
    protected static string $resource = FleetVnodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New VNode')
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false),

            Actions\Action::make('sync_binarylane')
                ->label('Sync')
                ->tooltip('Sync from BinaryLane')
                ->icon(Heroicon::OutlinedCloud)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync BinaryLane Servers')
                ->modalDescription('This will fetch all servers from BinaryLane and create/update VNodes for each one.')
                ->action(function () {
                    try {
                        $exitCode = Artisan::call('bl:sync');
                        $output = Artisan::output();

                        if ($exitCode !== 0) {
                            // Extract error message from output
                            preg_match('/Sync failed: (.+)$/m', $output, $errorMatch);
                            $error = $errorMatch[1] ?? 'Unknown error';
                            throw new \Exception($error);
                        }

                        // Count synced servers from output
                        preg_match('/Synced (\d+) servers/', $output, $matches);
                        $count = $matches[1] ?? '0';

                        // Also get created/updated counts
                        preg_match('/Created: (\d+)/', $output, $createdMatch);
                        preg_match('/Updated: (\d+)/', $output, $updatedMatch);
                        $created = $createdMatch[1] ?? 0;
                        $updated = $updatedMatch[1] ?? 0;

                        Notification::make()
                            ->title('BinaryLane Sync Complete')
                            ->body("Synced {$count} servers ({$created} created, {$updated} updated).")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn () => (bool) config('fleet.binarylane.api_token')),
        ];
    }
}
