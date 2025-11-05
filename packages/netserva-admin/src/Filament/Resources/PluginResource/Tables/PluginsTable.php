<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\PluginResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class PluginsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->boolean()
                    ->sortable()
                    ->label('Status')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->sortable()
                    ->placeholder('General')
                    ->color(fn (?string $state = null): string => match ($state) {
                        'infrastructure' => 'gray',
                        'service' => 'info',
                        'content' => 'warning',
                        'operations' => 'success',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('dependencies')
                    ->label('Dependencies')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Status')
                    ->placeholder('All plugins')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),

                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'infrastructure' => 'Infrastructure',
                        'service' => 'Service',
                        'content' => 'Content',
                        'operations' => 'Operations',
                    ]),
            ])
            ->actions([
                Action::make('toggle')
                    ->label(fn ($record) => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(fn ($record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_enabled' => ! $record->is_enabled]);
                    }),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_enabled' => true])),

                    BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_enabled' => false])),
                ]),
            ])
            ->defaultSort('name')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
