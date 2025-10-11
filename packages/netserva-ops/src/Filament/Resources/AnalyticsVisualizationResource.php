<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Pages\CreateAnalyticsVisualization;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Pages\EditAnalyticsVisualization;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Pages\ListAnalyticsVisualizations;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Schemas\AnalyticsVisualizationForm;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Tables\AnalyticsVisualizationsTable;
use NetServa\Ops\Models\AnalyticsVisualization;

class AnalyticsVisualizationResource extends Resource
{
    protected static ?string $model = AnalyticsVisualization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return AnalyticsVisualizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsVisualizationsTable::configure($table);
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
            'index' => ListAnalyticsVisualizations::route('/'),
            'create' => CreateAnalyticsVisualization::route('/create'),
            'edit' => EditAnalyticsVisualization::route('/{record}/edit'),
        ];
    }
}
