<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Pages\CreateIpNetwork;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Pages\EditIpNetwork;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Pages\ListIpNetworks;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Schemas\IpNetworkForm;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Tables\IpNetworksTable;
use NetServa\Fleet\Models\IpNetwork;
use UnitEnum;

class IpNetworkResource extends Resource
{
    protected static ?string $model = IpNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 2;  // Alphabetical: Ip Networks

    public static function form(Schema $schema): Schema
    {
        return IpNetworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IpNetworksTable::configure($table);
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
            'index' => ListIpNetworks::route('/'),
            'create' => CreateIpNetwork::route('/create'),
            'edit' => EditIpNetwork::route('/{record}/edit'),
        ];
    }
}
