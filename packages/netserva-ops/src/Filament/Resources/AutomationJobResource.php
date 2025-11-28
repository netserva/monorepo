<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AutomationJobResource\Pages\CreateAutomationJob;
use NetServa\Ops\Filament\Resources\AutomationJobResource\Pages\EditAutomationJob;
use NetServa\Ops\Filament\Resources\AutomationJobResource\Pages\ListAutomationJobs;
use NetServa\Ops\Filament\Resources\AutomationJobResource\Schemas\AutomationJobForm;
use NetServa\Ops\Filament\Resources\AutomationJobResource\Tables\AutomationJobsTable;
use NetServa\Ops\Models\AutomationJob;
use UnitEnum;

class AutomationJobResource extends Resource
{
    protected static ?string $model = AutomationJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static UnitEnum|string|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return AutomationJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationJobsTable::configure($table);
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
            'index' => ListAutomationJobs::route('/'),
            'create' => CreateAutomationJob::route('/create'),
            'edit' => EditAutomationJob::route('/{record}/edit'),
        ];
    }
}
