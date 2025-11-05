<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function make(Schema $schema): Schema
    {
        return $schema->components([
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
        ]);
    }
}
