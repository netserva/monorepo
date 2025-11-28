<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Clusters\Config\ConfigCluster;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource\Pages\CreateDatabaseCredential;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource\Pages\EditDatabaseCredential;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource\Pages\ListDatabaseCredentials;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource\Schemas\DatabaseCredentialForm;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource\Tables\DatabaseCredentialsTable;
use NetServa\Config\Models\DatabaseCredential;

class DatabaseCredentialResource extends Resource
{
    protected static ?string $model = DatabaseCredential::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $cluster = ConfigCluster::class;

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return DatabaseCredentialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabaseCredentialsTable::configure($table);
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
            'index' => ListDatabaseCredentials::route('/'),
            'create' => CreateDatabaseCredential::route('/create'),
            'edit' => EditDatabaseCredential::route('/{record}/edit'),
        ];
    }
}
