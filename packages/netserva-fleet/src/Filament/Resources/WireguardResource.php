<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\WireguardResource\Pages\ListServers;
use NetServa\Fleet\Filament\Resources\WireguardResource\Pages\ManagePeers;
use NetServa\Fleet\Filament\Resources\WireguardResource\Schemas\ServerForm;
use NetServa\Fleet\Filament\Resources\WireguardResource\Tables\ServersTable;
use NetServa\Fleet\Models\WireguardServer;
use UnitEnum;

/**
 * Unified WireGuard Resource
 *
 * Manages WireGuard VPN servers with drill-down access to peers.
 * Consolidates WireguardServer and WireguardPeer management.
 */
class WireguardResource extends Resource
{
    protected static ?string $model = WireguardServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'WireGuard';

    protected static ?string $modelLabel = 'Server';

    protected static ?string $pluralModelLabel = 'Servers';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ServerForm::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return ServersTable::make($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServers::route('/'),
            'peers' => ManagePeers::route('/{record}/peers'),
        ];
    }
}
