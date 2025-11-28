<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages\CreateDomainRegistrar;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages\EditDomainRegistrar;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages\ListDomainRegistrars;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Schemas\DomainRegistrarForm;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Tables\DomainRegistrarsTable;
use NetServa\Dns\Models\DomainRegistrar;
use UnitEnum;

class DomainRegistrarResource extends Resource
{
    protected static ?string $model = DomainRegistrar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return DomainRegistrarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainRegistrarsTable::configure($table);
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
            'index' => ListDomainRegistrars::route('/'),
            'create' => CreateDomainRegistrar::route('/create'),
            'edit' => EditDomainRegistrar::route('/{record}/edit'),
        ];
    }
}
