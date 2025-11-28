<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Clusters\Network\NetworkCluster;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Pages\CreateWireguardPeer;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Pages\EditWireguardPeer;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Pages\ListWireguardPeers;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Schemas\WireguardPeerForm;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Tables\WireguardPeersTable;
use NetServa\Fleet\Models\WireguardPeer;

class WireguardPeerResource extends Resource
{
    protected static ?string $model = WireguardPeer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $cluster = NetworkCluster::class;

    protected static ?int $navigationSort = 50;

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
