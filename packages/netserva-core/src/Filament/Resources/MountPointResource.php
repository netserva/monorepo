<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\MountPointResource\Pages\CreateMountPoint;
use NetServa\Core\Filament\Resources\MountPointResource\Pages\EditMountPoint;
use NetServa\Core\Filament\Resources\MountPointResource\Pages\ListMountPoints;
use NetServa\Core\Filament\Resources\MountPointResource\Schemas\MountPointForm;
use NetServa\Core\Filament\Resources\MountPointResource\Tables\MountPointsTable;
use NetServa\Core\Models\MountPoint;

class MountPointResource extends Resource
{
    protected static ?string $model = MountPoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return MountPointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MountPointsTable::configure($table);
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
            'index' => ListMountPoints::route('/'),
            'create' => CreateMountPoint::route('/create'),
            'edit' => EditMountPoint::route('/{record}/edit'),
        ];
    }
}
