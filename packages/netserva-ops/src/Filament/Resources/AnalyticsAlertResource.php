<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Pages\CreateAnalyticsAlert;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Pages\EditAnalyticsAlert;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Pages\ListAnalyticsAlerts;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Schemas\AnalyticsAlertForm;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Tables\AnalyticsAlertsTable;
use NetServa\Ops\Models\AnalyticsAlert;
use UnitEnum;

class AnalyticsAlertResource extends Resource
{
    protected static ?string $model = AnalyticsAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 24;

    public static function form(Schema $schema): Schema
    {
        return AnalyticsAlertForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsAlertsTable::configure($table);
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
            'index' => ListAnalyticsAlerts::route('/'),
            'create' => CreateAnalyticsAlert::route('/create'),
            'edit' => EditAnalyticsAlert::route('/{record}/edit'),
        ];
    }
}
