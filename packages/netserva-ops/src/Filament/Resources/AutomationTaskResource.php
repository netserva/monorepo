<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\AutomationTaskResource\Pages\CreateAutomationTask;
use NetServa\Ops\Filament\Resources\AutomationTaskResource\Pages\EditAutomationTask;
use NetServa\Ops\Filament\Resources\AutomationTaskResource\Pages\ListAutomationTasks;
use NetServa\Ops\Filament\Resources\AutomationTaskResource\Schemas\AutomationTaskForm;
use NetServa\Ops\Filament\Resources\AutomationTaskResource\Tables\AutomationTasksTable;
use NetServa\Ops\Models\AutomationTask;
use UnitEnum;

class AutomationTaskResource extends Resource
{
    protected static ?string $model = AutomationTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static UnitEnum|string|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 71;

    public static function form(Schema $schema): Schema
    {
        return AutomationTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationTasksTable::configure($table);
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
            'index' => ListAutomationTasks::route('/'),
            'create' => CreateAutomationTask::route('/create'),
            'edit' => EditAutomationTask::route('/{record}/edit'),
        ];
    }
}
