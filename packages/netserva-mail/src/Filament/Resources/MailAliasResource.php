<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailAliasResource\Pages\CreateMailAlias;
use NetServa\Mail\Filament\Resources\MailAliasResource\Pages\EditMailAlias;
use NetServa\Mail\Filament\Resources\MailAliasResource\Pages\ListMailAliases;
use NetServa\Mail\Filament\Resources\MailAliasResource\Schemas\MailAliasForm;
use NetServa\Mail\Filament\Resources\MailAliasResource\Tables\MailAliasesTable;
use NetServa\Mail\Models\MailAlias;
use UnitEnum;

class MailAliasResource extends Resource
{
    protected static ?string $model = MailAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return MailAliasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailAliasesTable::configure($table);
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
            'index' => ListMailAliases::route('/'),
            'create' => CreateMailAlias::route('/create'),
            'edit' => EditMailAlias::route('/{record}/edit'),
        ];
    }
}
