<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailDomainResource\Pages\CreateMailDomain;
use NetServa\Mail\Filament\Resources\MailDomainResource\Pages\EditMailDomain;
use NetServa\Mail\Filament\Resources\MailDomainResource\Pages\ListMailDomains;
use NetServa\Mail\Filament\Resources\MailDomainResource\Schemas\MailDomainForm;
use NetServa\Mail\Filament\Resources\MailDomainResource\Tables\MailDomainsTable;
use NetServa\Mail\Models\MailDomain;
use UnitEnum;

class MailDomainResource extends Resource
{
    protected static ?string $model = MailDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MailDomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailDomainsTable::configure($table);
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
            'index' => ListMailDomains::route('/'),
            'create' => CreateMailDomain::route('/create'),
            'edit' => EditMailDomain::route('/{record}/edit'),
        ];
    }
}
