<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\WireguardResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\WireguardResource;
use NetServa\Fleet\Filament\Resources\WireguardResource\Schemas\ServerForm;
use NetServa\Fleet\Models\WireguardServer;

class ServersTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('network_cidr')
                    ->label('Network')
                    ->fontFamily('mono')
                    ->sortable(),

                TextColumn::make('listen_port')
                    ->label('Port')
                    ->sortable(),

                TextColumn::make('endpoint')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('peers_count')
                    ->label('Peers')
                    ->counts('peers')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('vnode.name')
                    ->label('Host')
                    ->searchable()
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([])
            ->header(null)
            ->recordActions([
                Action::make('peers')
                    ->hiddenLabel()
                    ->tooltip('Manage Peers')
                    ->icon(Heroicon::OutlinedUsers)
                    ->color('info')
                    ->url(fn (WireguardServer $record) => WireguardResource::getUrl('peers', ['record' => $record])),

                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit Server')
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => ServerForm::getFormSchema()),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }
}
