<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Pages\CreateDnsProvider;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Pages\EditDnsProvider;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Pages\ListDnsProviders;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Schemas\DnsProviderForm;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Tables\DnsProvidersTable;
use NetServa\Dns\Models\DnsProvider;
use UnitEnum;

class DnsProviderResource extends Resource
{
    protected static ?string $model = DnsProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return DnsProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DnsProvidersTable::configure($table);
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
            'index' => ListDnsProviders::route('/'),
            'create' => CreateDnsProvider::route('/create'),
            'edit' => EditDnsProvider::route('/{record}/edit'),
        ];
    }
}
