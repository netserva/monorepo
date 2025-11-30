<?php

namespace NetServa\Fleet\Filament\Resources\IpAddressResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpAddressResource;
use NetServa\Fleet\Models\IpAddress;

class IpAddressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('hostname')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'allocated' => 'info',
                        'reserved' => 'warning',
                        'dhcp_pool' => 'primary',
                        'network', 'broadcast', 'gateway' => 'gray',
                        'dns', 'ntp' => 'primary',
                        'blacklisted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('ipNetwork.network_address')
                    ->label('Network')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('mac_address')
                    ->label('MAC Address')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('owner')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('service')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('vnode.name')
                    ->label('VNode')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('allocated_at')
                    ->label('Allocated')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(IpAddress::STATUSES)
                    ->multiple(),

                SelectFilter::make('ip_network_id')
                    ->label('Network')
                    ->relationship('ipNetwork', 'network_address')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit IP address')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => IpAddressResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete IP address'),
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
