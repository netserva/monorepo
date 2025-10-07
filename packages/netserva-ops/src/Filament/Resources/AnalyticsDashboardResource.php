<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Pages\CreateAnalyticsDashboard;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Pages\EditAnalyticsDashboard;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Pages\ListAnalyticsDashboards;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Schemas\AnalyticsDashboardForm;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Tables\AnalyticsDashboardsTable;
use NetServa\Ops\Models\AnalyticsDashboard;

class AnalyticsDashboardResource extends Resource
{
    protected static ?string $model = AnalyticsDashboard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AnalyticsDashboardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsDashboardsTable::configure($table);
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
            'index' => ListAnalyticsDashboards::route('/'),
            'create' => CreateAnalyticsDashboard::route('/create'),
            'edit' => EditAnalyticsDashboard::route('/{record}/edit'),
        ];
    }
}
