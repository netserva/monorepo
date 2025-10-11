<?php

namespace NetServa\Ipam\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ipam\Filament\Resources\IpAddressResource\Pages\CreateIpAddress;
use NetServa\Ipam\Filament\Resources\IpAddressResource\Pages\EditIpAddress;
use NetServa\Ipam\Filament\Resources\IpAddressResource\Pages\ListIpAddresses;
use NetServa\Ipam\Filament\Resources\IpAddressResource\Schemas\IpAddressForm;
use NetServa\Ipam\Filament\Resources\IpAddressResource\Tables\IpAddressesTable;
use NetServa\Ipam\Models\IpAddress;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'IP Address Management';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return IpAddressForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IpAddressesTable::configure($table);
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
            'index' => ListIpAddresses::route('/'),
            'create' => CreateIpAddress::route('/create'),
            'edit' => EditIpAddress::route('/{record}/edit'),
        ];
    }
}
