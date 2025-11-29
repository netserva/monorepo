<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SetupComponentResource\Pages\CreateSetupComponent;
use NetServa\Core\Filament\Resources\SetupComponentResource\Pages\EditSetupComponent;
use NetServa\Core\Filament\Resources\SetupComponentResource\Pages\ListSetupComponents;
use NetServa\Core\Filament\Resources\SetupComponentResource\Schemas\SetupComponentForm;
use NetServa\Core\Filament\Resources\SetupComponentResource\Tables\SetupComponentsTable;
use NetServa\Core\Models\SetupComponent;
use UnitEnum;

class SetupComponentResource extends Resource
{
    protected static ?string $model = SetupComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Cli';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return SetupComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SetupComponentsTable::configure($table);
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
            'index' => ListSetupComponents::route('/'),
            'create' => CreateSetupComponent::route('/create'),
            'edit' => EditSetupComponent::route('/{record}/edit'),
        ];
    }
}
