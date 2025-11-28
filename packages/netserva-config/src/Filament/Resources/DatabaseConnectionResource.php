<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Clusters\Config\ConfigCluster;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource\Pages\CreateDatabaseConnection;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource\Pages\EditDatabaseConnection;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource\Pages\ListDatabaseConnections;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource\Schemas\DatabaseConnectionForm;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource\Tables\DatabaseConnectionsTable;
use NetServa\Config\Models\DatabaseConnection;

class DatabaseConnectionResource extends Resource
{
    protected static ?string $model = DatabaseConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $cluster = ConfigCluster::class;

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return DatabaseConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabaseConnectionsTable::configure($table);
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
            'index' => ListDatabaseConnections::route('/'),
            'create' => CreateDatabaseConnection::route('/create'),
            'edit' => EditDatabaseConnection::route('/{record}/edit'),
        ];
    }
}
