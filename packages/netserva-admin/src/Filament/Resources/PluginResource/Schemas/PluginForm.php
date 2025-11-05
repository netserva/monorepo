<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\PluginResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PluginForm
{
    public static function make(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Plugin Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->disabled()
                        ->label('Plugin Name'),

                    Forms\Components\TextInput::make('plugin_class')
                        ->disabled()
                        ->label('Class'),

                    Forms\Components\TextInput::make('package_name')
                        ->disabled()
                        ->label('Package'),

                    Forms\Components\TextInput::make('version')
                        ->disabled(),

                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->helperText('Enable or disable this plugin'),

                    Forms\Components\Textarea::make('description')
                        ->disabled()
                        ->rows(2),
                ]),

            Forms\Components\Section::make('Dependencies')
                ->schema([
                    Forms\Components\TagsInput::make('dependencies')
                        ->disabled()
                        ->helperText('Required plugins'),
                ])
                ->visible(fn ($record) => ! empty($record?->dependencies)),

            Forms\Components\Section::make('Metadata')
                ->schema([
                    Forms\Components\TextInput::make('source')
                        ->disabled(),

                    Forms\Components\TextInput::make('category')
                        ->disabled(),

                    Forms\Components\KeyValue::make('composer_data')
                        ->disabled()
                        ->label('Composer Data'),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
