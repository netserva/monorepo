<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                EditAction::make()
                    ->form([
                        Section::make('Setting Details')
                            ->schema([
                                // Row 1: Key and Value
                                Forms\Components\TextInput::make('key')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g., mail.driver, dns.provider')
                                    ->helperText('Use dot notation for hierarchy (e.g., mail.driver)'),

                                // Value field for string type
                                Forms\Components\TextInput::make('value_string')
                                    ->label('Value')
                                    ->required(fn (Get $get): bool => $get('type') === 'string')
                                    ->visible(fn (Get $get): bool => $get('type') === 'string')
                                    ->placeholder('Setting value'),

                                // Value field for integer type
                                Forms\Components\TextInput::make('value_integer')
                                    ->label('Value')
                                    ->numeric()
                                    ->required(fn (Get $get): bool => $get('type') === 'integer')
                                    ->visible(fn (Get $get): bool => $get('type') === 'integer')
                                    ->placeholder('0'),

                                // Value field for boolean type
                                Forms\Components\Toggle::make('value_boolean')
                                    ->label('Value')
                                    ->visible(fn (Get $get): bool => $get('type') === 'boolean'),

                                // Value field for JSON type
                                Forms\Components\TextInput::make('value_json')
                                    ->label('Value')
                                    ->required(fn (Get $get): bool => $get('type') === 'json')
                                    ->visible(fn (Get $get): bool => $get('type') === 'json')
                                    ->placeholder('{"key": "value"}')
                                    ->helperText('Valid JSON format required'),

                                // Row 2: Type and Category
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'string' => 'String',
                                        'integer' => 'Integer',
                                        'boolean' => 'Boolean',
                                        'json' => 'JSON',
                                    ])
                                    ->default('string')
                                    ->required()
                                    ->live()
                                    ->helperText('Data type for the value'),

                                Forms\Components\TextInput::make('category')
                                    ->maxLength(255)
                                    ->placeholder('e.g., mail, dns, web')
                                    ->helperText('Optional: Group settings by category'),

                                // Row 3: Description (full width)
                                Forms\Components\TextInput::make('description')
                                    ->maxLength(500)
                                    ->placeholder('Optional description of this setting')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Map database 'value' column to type-specific form field
                        $value = $data['value'] ?? null;
                        $type = $data['type'] ?? 'string';

                        // Set the appropriate typed field based on type
                        match ($type) {
                            'string' => $data['value_string'] = $value,
                            'integer' => $data['value_integer'] = $value,
                            'boolean' => $data['value_boolean'] = $value,
                            'json' => $data['value_json'] = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value,
                            default => $data['value_string'] = $value,
                        };

                        // Don't pass 'value' to avoid hydration conflicts
                        unset($data['value']);

                        return $data;
                    })
                    ->using(function ($record, array $data): bool {
                        // Map type-specific form field back to database 'value' column
                        $data['value'] = match ($data['type'] ?? 'string') {
                            'string' => $data['value_string'] ?? '',
                            'integer' => $data['value_integer'] ?? 0,
                            'boolean' => ($data['value_boolean'] ?? false) ? '1' : '0',
                            'json' => $data['value_json'] ?? '{}',
                            default => $data['value_string'] ?? '',
                        };

                        // Clean up temporary typed fields
                        unset($data['value_string'], $data['value_integer'], $data['value_boolean'], $data['value_json']);

                        // Update the record
                        $record->update($data);

                        return true;
                    })
                    ->modalWidth('2xl'),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc') // Most recently edited at top
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
