<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SettingResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g., mail.driver')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Use dot notation for hierarchy (e.g., mail.driver)'),

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
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Data type for the value'),
            ]),

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
            Forms\Components\Textarea::make('value_json')
                ->label('Value')
                ->required(fn (Get $get): bool => $get('type') === 'json')
                ->visible(fn (Get $get): bool => $get('type') === 'json')
                ->rows(3)
                ->placeholder('{"key": "value"}')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Valid JSON format required'),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('category')
                    ->maxLength(255)
                    ->placeholder('e.g., mail, dns, web')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Optional: Group settings by category'),

                Forms\Components\TextInput::make('description')
                    ->maxLength(500)
                    ->placeholder('Optional description'),
            ]),
        ];
    }

    public static function make(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }
}
