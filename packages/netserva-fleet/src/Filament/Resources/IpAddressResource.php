<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Pages\CreateIpAddress;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Pages\EditIpAddress;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Pages\ListIpAddresses;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Schemas\IpAddressForm;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Tables\IpAddressesTable;
use NetServa\Fleet\Models\IpAddress;
use UnitEnum;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 1;  // Alphabetical: Ip Addresses

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
