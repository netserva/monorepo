<?php

namespace NetServa\Wg\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Wg\Filament\Resources\WireguardPeerResource\Pages\CreateWireguardPeer;
use NetServa\Wg\Filament\Resources\WireguardPeerResource\Pages\EditWireguardPeer;
use NetServa\Wg\Filament\Resources\WireguardPeerResource\Pages\ListWireguardPeers;
use NetServa\Wg\Filament\Resources\WireguardPeerResource\Schemas\WireguardPeerForm;
use NetServa\Wg\Filament\Resources\WireguardPeerResource\Tables\WireguardPeersTable;
use NetServa\Wg\Models\WireguardPeer;
use UnitEnum;

class WireguardPeerResource extends Resource
{
    protected static ?string $model = WireguardPeer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Wg';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return WireguardPeerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WireguardPeersTable::configure($table);
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
            'index' => ListWireguardPeers::route('/'),
            'create' => CreateWireguardPeer::route('/create'),
            'edit' => EditWireguardPeer::route('/{record}/edit'),
        ];
    }
}
