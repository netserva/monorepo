<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Pages\CreateAnalyticsDataSource;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Pages\EditAnalyticsDataSource;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Pages\ListAnalyticsDataSources;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Schemas\AnalyticsDataSourceForm;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Tables\AnalyticsDataSourcesTable;
use NetServa\Ops\Models\AnalyticsDataSource;

class AnalyticsDataSourceResource extends Resource
{
    protected static ?string $model = AnalyticsDataSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return AnalyticsDataSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsDataSourcesTable::configure($table);
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
            'index' => ListAnalyticsDataSources::route('/'),
            'create' => CreateAnalyticsDataSource::route('/create'),
            'edit' => EditAnalyticsDataSource::route('/{record}/edit'),
        ];
    }
}
