<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource\Pages\CreateMonitoringCheck;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource\Pages\EditMonitoringCheck;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource\Pages\ListMonitoringChecks;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource\Schemas\MonitoringCheckForm;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource\Tables\MonitoringChecksTable;
use NetServa\Ops\Models\MonitoringCheck;
use UnitEnum;

class MonitoringCheckResource extends Resource
{
    protected static ?string $model = MonitoringCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MonitoringCheckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonitoringChecksTable::configure($table);
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
            'index' => ListMonitoringChecks::route('/'),
            'create' => CreateMonitoringCheck::route('/create'),
            'edit' => EditMonitoringCheck::route('/{record}/edit'),
        ];
    }
}
