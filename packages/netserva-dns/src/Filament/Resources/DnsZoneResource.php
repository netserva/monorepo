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
use UnitEnum;

class DnsZoneResource extends Resource
{
    protected static ?string $model = DnsZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'DNS & Domains';

    protected static ?int $navigationSort = 10;

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
            DnsZoneResource\RelationManagers\RecordsRelationManager::class,
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
