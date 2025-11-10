<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DnsZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => rtrim($state, '.'))
                    ->description(fn ($record) => $record->description)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('dnsProvider.name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'Native' => 'success',
                        'Master', 'Primary' => 'primary',
                        'Slave', 'Secondary' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('records_count')
                    ->label('Records')
                    ->counts('records')
                    ->sortable()
                    ->default(0),

                Tables\Columns\IconColumn::make('dnssec_enabled')
                    ->label('DNSSEC')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ttl')
                    ->label('TTL')
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(fn ($state) => $state.'s'),

                Tables\Columns\TextColumn::make('serial')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dns_provider_id')
                    ->label('Provider')
                    ->relationship('dnsProvider', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        'Native' => 'Native',
                        'Master' => 'Master',
                        'Primary' => 'Primary',
                        'Slave' => 'Slave',
                        'Secondary' => 'Secondary',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Active Status')
                    ->placeholder('All zones')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('active', true),
                        false: fn (Builder $query) => $query->where('active', false),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\TernaryFilter::make('dnssec_enabled')
                    ->label('DNSSEC')
                    ->placeholder('All zones')
                    ->trueLabel('DNSSEC enabled')
                    ->falseLabel('DNSSEC disabled'),

                Tables\Filters\Filter::make('has_records')
                    ->label('Has Records')
                    ->query(fn (Builder $query): Builder => $query->has('records'))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
