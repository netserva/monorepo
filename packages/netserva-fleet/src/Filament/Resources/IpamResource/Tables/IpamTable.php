<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\IpamResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpamResource;
use NetServa\Fleet\Filament\Resources\IpamResource\Schemas\NetworkForm;
use NetServa\Fleet\Models\IpNetwork;
use NetServa\Fleet\Services\IpamDiscoveryService;

class IpamTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->tooltip(fn (IpNetwork $record) => $record->description),

                TextColumn::make('cidr')
                    ->label('CIDR')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('CIDR copied'),

                TextColumn::make('network_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'private' => 'info',
                        'dmz' => 'warning',
                        'management' => 'primary',
                        'vpn' => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('ip_version')
                    ->label('Ver')
                    ->badge()
                    ->color(fn (string $state): string => $state === '4' ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state): string => 'v'.$state),

                TextColumn::make('gateway')
                    ->fontFamily('mono')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('ip_addresses_count')
                    ->label('IPs')
                    ->counts('ipAddresses')
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('ip_reservations_count')
                    ->label('Reservations')
                    ->counts('ipReservations')
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('network_type')
                    ->options(IpNetwork::NETWORK_TYPES),

                SelectFilter::make('ip_version')
                    ->label('IP Version')
                    ->options([
                        '4' => 'IPv4',
                        '6' => 'IPv6',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordUrl(fn (IpNetwork $record) => IpamResource::getUrl('addresses', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit network')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => NetworkForm::getFormSchema()),

                // Scan network for live hosts
                Action::make('discover')
                    ->hiddenLabel()
                    ->tooltip('Scan for live hosts')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn (IpNetwork $record) => "Network Scan: {$record->name}")
                    ->modalDescription(fn (IpNetwork $record) => "Ping sweep {$record->cidr} to discover live hosts. This may take a few seconds.")
                    ->modalSubmitActionLabel('Start Scan')
                    ->modalWidth(Width::Small)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->action(function (IpNetwork $record) {
                        $service = app(IpamDiscoveryService::class);
                        $stats = $service->scanNetwork($record);

                        if ($stats['hosts_alive'] > 0) {
                            Notification::make()
                                ->title('Scan Complete')
                                ->body(sprintf(
                                    'Found %d live hosts (%d new, %d updated) in %s',
                                    $stats['hosts_alive'],
                                    $stats['addresses_created'],
                                    $stats['addresses_updated'],
                                    $record->cidr
                                ))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Hosts Found')
                                ->body(sprintf(
                                    'No live hosts found in %s (scanned %d addresses)',
                                    $record->cidr,
                                    $stats['hosts_scanned']
                                ))
                                ->info()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete network'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }
}
