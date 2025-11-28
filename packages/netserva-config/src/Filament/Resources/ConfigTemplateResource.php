<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Resources\ConfigTemplateResource\Pages\CreateConfigTemplate;
use NetServa\Config\Filament\Resources\ConfigTemplateResource\Pages\EditConfigTemplate;
use NetServa\Config\Filament\Resources\ConfigTemplateResource\Pages\ListConfigTemplates;
use NetServa\Config\Filament\Resources\ConfigTemplateResource\Schemas\ConfigTemplateForm;
use NetServa\Config\Filament\Resources\ConfigTemplateResource\Tables\ConfigTemplatesTable;
use NetServa\Config\Models\ConfigTemplate;
use UnitEnum;

class ConfigTemplateResource extends Resource
{
    protected static ?string $model = ConfigTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ConfigTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigTemplates::route('/'),
            'create' => CreateConfigTemplate::route('/create'),
            'edit' => EditConfigTemplate::route('/{record}/edit'),
        ];
    }
}
