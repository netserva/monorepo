<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DnsZoneResource\Pages\CreateDnsZone;
use NetServa\Dns\Filament\Resources\DnsZoneResource\Pages\EditDnsZone;
use NetServa\Dns\Filament\Resources\DnsZoneResource\Pages\ListDnsZones;
use NetServa\Dns\Filament\Resources\DnsZoneResource\Schemas\DnsZoneForm;
use NetServa\Dns\Filament\Resources\DnsZoneResource\Tables\DnsZonesTable;
use NetServa\Dns\Models\DnsZone;

class DnsZoneResource extends Resource
{
    protected static ?string $model = DnsZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DnsZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DnsZonesTable::configure($table);
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
            'index' => ListDnsZones::route('/'),
            'create' => CreateDnsZone::route('/create'),
            'edit' => EditDnsZone::route('/{record}/edit'),
        ];
    }
}
