<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class SettingsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn ($record) => $record->category)
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('value')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => strlen((string) $record->value) > 50 ? $record->value : null)
                    ->formatStateUsing(function ($state, $record) {
                        return match ($record->type) {
                            'boolean' => $state ? '✓ True' : '✗ False',
                            'json' => is_array($state) ? json_encode($state) : $state,
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'string' => 'gray',
                        'integer' => 'info',
                        'boolean' => 'warning',
                        'json' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Uncategorized'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->options(function () {
                        return \NetServa\Core\Models\Setting::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('key')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
