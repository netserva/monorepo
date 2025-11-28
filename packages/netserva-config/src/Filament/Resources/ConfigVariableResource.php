<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Clusters\Config\ConfigCluster;
use NetServa\Config\Filament\Resources\ConfigVariableResource\Pages\CreateConfigVariable;
use NetServa\Config\Filament\Resources\ConfigVariableResource\Pages\EditConfigVariable;
use NetServa\Config\Filament\Resources\ConfigVariableResource\Pages\ListConfigVariables;
use NetServa\Config\Filament\Resources\ConfigVariableResource\Schemas\ConfigVariableForm;
use NetServa\Config\Filament\Resources\ConfigVariableResource\Tables\ConfigVariablesTable;
use NetServa\Config\Models\ConfigVariable;

class ConfigVariableResource extends Resource
{
    protected static ?string $model = ConfigVariable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static ?string $cluster = ConfigCluster::class;

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return ConfigVariableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigVariablesTable::configure($table);
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
            'index' => ListConfigVariables::route('/'),
            'create' => CreateConfigVariable::route('/create'),
            'edit' => EditConfigVariable::route('/{record}/edit'),
        ];
    }
}
