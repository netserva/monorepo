<?php

namespace NetServa\Wg\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Wg\Filament\Resources\WireguardServerResource\Pages\CreateWireguardServer;
use NetServa\Wg\Filament\Resources\WireguardServerResource\Pages\EditWireguardServer;
use NetServa\Wg\Filament\Resources\WireguardServerResource\Pages\ListWireguardServers;
use NetServa\Wg\Filament\Resources\WireguardServerResource\Schemas\WireguardServerForm;
use NetServa\Wg\Filament\Resources\WireguardServerResource\Tables\WireguardServersTable;
use NetServa\Wg\Models\WireguardServer;
use UnitEnum;

class WireguardServerResource extends Resource
{
    protected static ?string $model = WireguardServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Wg';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return WireguardServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WireguardServersTable::configure($table);
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
            'index' => ListWireguardServers::route('/'),
            'create' => CreateWireguardServer::route('/create'),
            'edit' => EditWireguardServer::route('/{record}/edit'),
        ];
    }
}
