<?php

namespace NetServa\Ipam\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ipam\Filament\Resources\IpNetworkResource\Pages\CreateIpNetwork;
use NetServa\Ipam\Filament\Resources\IpNetworkResource\Pages\EditIpNetwork;
use NetServa\Ipam\Filament\Resources\IpNetworkResource\Pages\ListIpNetworks;
use NetServa\Ipam\Filament\Resources\IpNetworkResource\Schemas\IpNetworkForm;
use NetServa\Ipam\Filament\Resources\IpNetworkResource\Tables\IpNetworksTable;
use NetServa\Ipam\Models\IpNetwork;

class IpNetworkResource extends Resource
{
    protected static ?string $model = IpNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'IP Address Management';

    protected static ?int $navigationSort = 10;

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
