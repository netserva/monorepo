<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages\CreateDomainRegistration;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages\EditDomainRegistration;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages\ListDomainRegistrations;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Schemas\DomainRegistrationForm;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource\Tables\DomainRegistrationsTable;
use NetServa\Dns\Models\DomainRegistration;

class DomainRegistrationResource extends Resource
{
    protected static ?string $model = DomainRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'DNS & Domains';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return DomainRegistrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainRegistrationsTable::configure($table);
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
            'index' => ListDomainRegistrations::route('/'),
            'create' => CreateDomainRegistration::route('/create'),
            'edit' => EditDomainRegistration::route('/{record}/edit'),
        ];
    }
}
