<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrarResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;
use NetServa\Dns\Models\DomainRegistrar;
use NetServa\Dns\Services\SynergyWholesaleService;

class DomainRegistrarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('registrar_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'synergywholesale' => 'success',
                        'namecheap' => 'warning',
                        'godaddy' => 'info',
                        'cloudflare' => 'primary',
                        'route53' => 'purple',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'testing' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('api_endpoint')
                    ->label('API Endpoint')
                    ->searchable()
                    ->toggleable()
                    ->limit(50)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('domain_registrations_count')
                    ->label('Domains')
                    ->counts('domainRegistrations')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('registrar_type')
                    ->label('Type')
                    ->options([
                        'synergywholesale' => 'SynergyWholesale',
                        'namecheap' => 'Namecheap',
                        'godaddy' => 'GoDaddy',
                        'cloudflare' => 'Cloudflare',
                        'route53' => 'Route53',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'testing' => 'Testing',
                    ]),
            ])
            ->searchable(false)
            ->recordActions([
                Action::make('sync_domains')
                    ->label('')
                    ->tooltip('Sync Domains')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (DomainRegistrar $record) => "Sync from {$record->name}")
                    ->modalDescription('This will fetch all active domains from this registrar and update the local database.')
                    ->action(function (DomainRegistrar $record) {
                        try {
                            $exitCode = Artisan::call('dns:sync-domains', [
                                '--registrar' => $record->id,
                            ]);
                            $output = Artisan::output();

                            if ($exitCode !== 0) {
                                preg_match('/Sync failed: (.+)$/m', $output, $errorMatch);
                                $error = $errorMatch[1] ?? 'Unknown error';
                                throw new \Exception($error);
                            }

                            preg_match('/Created: (\d+)/', $output, $createdMatch);
                            preg_match('/Updated: (\d+)/', $output, $updatedMatch);
                            $created = $createdMatch[1] ?? 0;
                            $updated = $updatedMatch[1] ?? 0;

                            Notification::make()
                                ->title('Sync Complete')
                                ->body("Created: {$created}, Updated: {$updated}")
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
                    ->visible(fn (DomainRegistrar $record) => $record->status === 'active'),
                Action::make('test_connection')
                    ->label('')
                    ->tooltip('Test Connection')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->action(function (DomainRegistrar $record) {
                        $result = match ($record->registrar_type) {
                            'synergywholesale' => (new SynergyWholesaleService($record))->testConnection(),
                            default => ['success' => false, 'message' => 'Test not implemented for this registrar type'],
                        };

                        if ($result['success']) {
                            $body = 'Connection successful.';
                            if (isset($result['balance'])) {
                                $body .= " Balance: \${$result['balance']}";
                            }
                            Notification::make()
                                ->title('Connection Test Passed')
                                ->body($body)
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection Test Failed')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit registrar')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => DomainRegistrarResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete registrar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }
}
