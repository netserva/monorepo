<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Resources\DatabaseResource\Pages\CreateDatabase;
use NetServa\Config\Filament\Resources\DatabaseResource\Pages\EditDatabase;
use NetServa\Config\Filament\Resources\DatabaseResource\Pages\ListDatabases;
use NetServa\Config\Filament\Resources\DatabaseResource\Schemas\DatabaseForm;
use NetServa\Config\Filament\Resources\DatabaseResource\Tables\DatabasesTable;
use NetServa\Config\Models\Database;
use UnitEnum;

class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return DatabaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabasesTable::configure($table);
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
            'index' => ListDatabases::route('/'),
            'create' => CreateDatabase::route('/create'),
            'edit' => EditDatabase::route('/{record}/edit'),
        ];
    }
}
