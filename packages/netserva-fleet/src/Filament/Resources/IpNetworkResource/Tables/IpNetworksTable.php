<?php

namespace NetServa\Fleet\Filament\Resources\IpNetworkResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpNetworkResource;
use NetServa\Fleet\Models\IpNetwork;

class IpNetworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('cidr')
                    ->label('CIDR')
                    ->searchable()
                    ->sortable()
                    ->color('gray')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('network_type')
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

                Tables\Columns\TextColumn::make('ip_version')
                    ->label('Version')
                    ->badge()
                    ->color(fn (string $state): string => $state === '4' ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state): string => 'IPv'.$state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->searchable()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('vnode.name')
                    ->label('VNode')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_addresses')
                    ->label('Total IPs')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('network_type')
                    ->options(IpNetwork::NETWORK_TYPES),

                Tables\Filters\SelectFilter::make('ip_version')
                    ->label('IP Version')
                    ->options([
                        '4' => 'IPv4',
                        '6' => 'IPv6',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All networks')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->searchable(false)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit network')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => IpNetworkResource::getFormSchema()),
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
            ->paginated([25, 50, 100]);
    }
}
