<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Resources\SecretResource\Pages\CreateSecret;
use NetServa\Config\Filament\Resources\SecretResource\Pages\EditSecret;
use NetServa\Config\Filament\Resources\SecretResource\Pages\ListSecrets;
use NetServa\Config\Filament\Resources\SecretResource\Schemas\SecretForm;
use NetServa\Config\Filament\Resources\SecretResource\Tables\SecretsTable;
use NetServa\Config\Models\Secret;

class SecretResource extends Resource
{
    protected static ?string $model = Secret::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SecretForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecretsTable::configure($table);
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
            'index' => ListSecrets::route('/'),
            'create' => CreateSecret::route('/create'),
            'edit' => EditSecret::route('/{record}/edit'),
        ];
    }
}
