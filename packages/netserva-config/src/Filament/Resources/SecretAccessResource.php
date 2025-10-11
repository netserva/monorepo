<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Resources\SecretAccessResource\Pages\CreateSecretAccess;
use NetServa\Config\Filament\Resources\SecretAccessResource\Pages\EditSecretAccess;
use NetServa\Config\Filament\Resources\SecretAccessResource\Pages\ListSecretAccesses;
use NetServa\Config\Filament\Resources\SecretAccessResource\Schemas\SecretAccessForm;
use NetServa\Config\Filament\Resources\SecretAccessResource\Tables\SecretAccessesTable;
use NetServa\Config\Models\SecretAccess;

class SecretAccessResource extends Resource
{
    protected static ?string $model = SecretAccess::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Secrets';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return SecretAccessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecretAccessesTable::configure($table);
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
            'index' => ListSecretAccesses::route('/'),
            'create' => CreateSecretAccess::route('/create'),
            'edit' => EditSecretAccess::route('/{record}/edit'),
        ];
    }

    // SecretAccess is an audit log - read-only
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
