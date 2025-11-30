<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SettingResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SettingResource\Schemas\SettingForm;

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
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('value')
                    ->limit(40)
                    ->tooltip(fn ($record) => strlen((string) $record->value) > 40 ? $record->value : null)
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

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit setting')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => SettingForm::getFormSchema())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $value = $data['value'] ?? null;
                        $type = $data['type'] ?? 'string';

                        match ($type) {
                            'string' => $data['value_string'] = $value,
                            'integer' => $data['value_integer'] = $value,
                            'boolean' => $data['value_boolean'] = $value,
                            'json' => $data['value_json'] = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value,
                            default => $data['value_string'] = $value,
                        };

                        unset($data['value']);

                        return $data;
                    })
                    ->using(function ($record, array $data): bool {
                        $data['value'] = match ($data['type'] ?? 'string') {
                            'string' => $data['value_string'] ?? '',
                            'integer' => $data['value_integer'] ?? 0,
                            'boolean' => ($data['value_boolean'] ?? false) ? '1' : '0',
                            'json' => $data['value_json'] ?? '{}',
                            default => $data['value_string'] ?? '',
                        };

                        unset($data['value_string'], $data['value_integer'], $data['value_boolean'], $data['value_json']);
                        $record->update($data);

                        return true;
                    }),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete setting'),
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
