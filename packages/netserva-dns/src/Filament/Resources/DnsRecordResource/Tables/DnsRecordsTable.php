<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DnsRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Content')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('ttl')
                    ->label('TTL')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? number_format($state).'s' : 'Auto'),
                TextColumn::make('dnsZone.name')
                    ->label('Zone')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
