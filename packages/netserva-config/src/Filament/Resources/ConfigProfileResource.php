<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Clusters\Config\ConfigCluster;
use NetServa\Config\Filament\Resources\ConfigProfileResource\Pages\CreateConfigProfile;
use NetServa\Config\Filament\Resources\ConfigProfileResource\Pages\EditConfigProfile;
use NetServa\Config\Filament\Resources\ConfigProfileResource\Pages\ListConfigProfiles;
use NetServa\Config\Filament\Resources\ConfigProfileResource\Schemas\ConfigProfileForm;
use NetServa\Config\Filament\Resources\ConfigProfileResource\Tables\ConfigProfilesTable;
use NetServa\Config\Models\ConfigProfile;

class ConfigProfileResource extends Resource
{
    protected static ?string $model = ConfigProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = ConfigCluster::class;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return ConfigProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigProfilesTable::configure($table);
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
            'index' => ListConfigProfiles::route('/'),
            'create' => CreateConfigProfile::route('/create'),
            'edit' => EditConfigProfile::route('/{record}/edit'),
        ];
    }
}
