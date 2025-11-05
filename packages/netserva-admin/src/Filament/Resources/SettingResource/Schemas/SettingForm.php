<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function make(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Setting Details')
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g., mail.driver, dns.provider')
                        ->helperText('Use dot notation for hierarchy (e.g., mail.driver)'),

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

                    Forms\Components\Textarea::make('value')
                        ->required()
                        ->rows(3)
                        ->visible(fn (Forms\Get $get) => $get('type') === 'string')
                        ->placeholder('Setting value'),

                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->numeric()
                        ->visible(fn (Forms\Get $get) => $get('type') === 'integer')
                        ->placeholder('0'),

                    Forms\Components\Toggle::make('value')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'boolean')
                        ->default(false),

                    Forms\Components\Textarea::make('value')
                        ->required()
                        ->rows(5)
                        ->visible(fn (Forms\Get $get) => $get('type') === 'json')
                        ->placeholder('{"key": "value"}')
                        ->helperText('Valid JSON format required'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(500)
                        ->rows(2)
                        ->placeholder('Optional description of this setting'),
                ])->columns(1),
        ]);
    }
}
