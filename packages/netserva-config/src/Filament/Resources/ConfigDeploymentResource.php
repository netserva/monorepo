<?php

namespace NetServa\Config\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource\Pages\CreateConfigDeployment;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource\Pages\EditConfigDeployment;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource\Pages\ListConfigDeployments;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource\Schemas\ConfigDeploymentForm;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource\Tables\ConfigDeploymentsTable;
use NetServa\Config\Models\ConfigDeployment;
use UnitEnum;

class ConfigDeploymentResource extends Resource
{
    protected static ?string $model = ConfigDeployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return ConfigDeploymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigDeploymentsTable::configure($table);
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
            'index' => ListConfigDeployments::route('/'),
            'create' => CreateConfigDeployment::route('/create'),
            'edit' => EditConfigDeployment::route('/{record}/edit'),
        ];
    }
}
