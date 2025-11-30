<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailDomainResource\Pages\ListMailDomains;
use NetServa\Mail\Filament\Resources\MailDomainResource\Schemas\MailDomainForm;
use NetServa\Mail\Filament\Resources\MailDomainResource\Tables\MailDomainsTable;
use NetServa\Mail\Models\MailDomain;
use UnitEnum;

class MailDomainResource extends Resource
{
    protected static ?string $model = MailDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static ?string $navigationLabel = 'Mail Domains';

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'domain';

    public static function getFormSchema(): array
    {
        return MailDomainForm::getFormSchema();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
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
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'domain', 'description'];
    }
}
