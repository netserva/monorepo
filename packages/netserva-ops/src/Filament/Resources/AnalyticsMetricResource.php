<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Clusters\Operations\OperationsCluster;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Pages\CreateAnalyticsMetric;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Pages\EditAnalyticsMetric;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Pages\ListAnalyticsMetrics;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Schemas\AnalyticsMetricForm;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Tables\AnalyticsMetricsTable;
use NetServa\Ops\Models\AnalyticsMetric;

class AnalyticsMetricResource extends Resource
{
    protected static ?string $model = AnalyticsMetric::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = OperationsCluster::class;

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return AnalyticsMetricForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsMetricsTable::configure($table);
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
            'index' => ListAnalyticsMetrics::route('/'),
            'create' => CreateAnalyticsMetric::route('/create'),
            'edit' => EditAnalyticsMetric::route('/{record}/edit'),
        ];
    }
}
