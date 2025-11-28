<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\WireguardServerResource\Pages\CreateWireguardServer;
use NetServa\Fleet\Filament\Resources\WireguardServerResource\Pages\EditWireguardServer;
use NetServa\Fleet\Filament\Resources\WireguardServerResource\Pages\ListWireguardServers;
use NetServa\Fleet\Filament\Resources\WireguardServerResource\Schemas\WireguardServerForm;
use NetServa\Fleet\Filament\Resources\WireguardServerResource\Tables\WireguardServersTable;
use NetServa\Fleet\Models\WireguardServer;
use UnitEnum;

class WireguardServerResource extends Resource
{
    protected static ?string $model = WireguardServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 40;

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
