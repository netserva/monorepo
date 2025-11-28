<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Pages\CreateDnsRecord;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Pages\EditDnsRecord;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Pages\ListDnsRecords;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Schemas\DnsRecordForm;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Tables\DnsRecordsTable;
use NetServa\Dns\Models\DnsRecord;
use UnitEnum;

class DnsRecordResource extends Resource
{
    protected static ?string $model = DnsRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return DnsRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DnsRecordsTable::configure($table);
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
            'index' => ListDnsRecords::route('/'),
            'create' => CreateDnsRecord::route('/create'),
            'edit' => EditDnsRecord::route('/{record}/edit'),
        ];
    }
}
