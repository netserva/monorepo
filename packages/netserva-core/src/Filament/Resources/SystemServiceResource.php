<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SystemServiceResource\Pages\CreateSystemService;
use NetServa\Core\Filament\Resources\SystemServiceResource\Pages\EditSystemService;
use NetServa\Core\Filament\Resources\SystemServiceResource\Pages\ListSystemServices;
use NetServa\Core\Filament\Resources\SystemServiceResource\Schemas\SystemServiceForm;
use NetServa\Core\Filament\Resources\SystemServiceResource\Tables\SystemServicesTable;
use NetServa\Core\Models\SystemService;

class SystemServiceResource extends Resource
{
    protected static ?string $model = SystemService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SystemServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemServicesTable::configure($table);
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
            'index' => ListSystemServices::route('/'),
            'create' => CreateSystemService::route('/create'),
            'edit' => EditSystemService::route('/{record}/edit'),
        ];
    }
}
