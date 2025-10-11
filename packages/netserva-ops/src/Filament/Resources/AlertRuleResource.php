<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AlertRuleResource\Pages\CreateAlertRule;
use NetServa\Ops\Filament\Resources\AlertRuleResource\Pages\EditAlertRule;
use NetServa\Ops\Filament\Resources\AlertRuleResource\Pages\ListAlertRules;
use NetServa\Ops\Filament\Resources\AlertRuleResource\Schemas\AlertRuleForm;
use NetServa\Ops\Filament\Resources\AlertRuleResource\Tables\AlertRulesTable;
use NetServa\Ops\Models\AlertRule;

class AlertRuleResource extends Resource
{
    protected static ?string $model = AlertRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return AlertRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlertRulesTable::configure($table);
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
            'index' => ListAlertRules::route('/'),
            'create' => CreateAlertRule::route('/create'),
            'edit' => EditAlertRule::route('/{record}/edit'),
        ];
    }
}
