<?php

namespace NetServa\Cron\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Cron\Filament\Resources\AutomationTaskResource\Pages\CreateAutomationTask;
use NetServa\Cron\Filament\Resources\AutomationTaskResource\Pages\EditAutomationTask;
use NetServa\Cron\Filament\Resources\AutomationTaskResource\Pages\ListAutomationTasks;
use NetServa\Cron\Filament\Resources\AutomationTaskResource\Schemas\AutomationTaskForm;
use NetServa\Cron\Filament\Resources\AutomationTaskResource\Tables\AutomationTasksTable;
use NetServa\Cron\Models\AutomationTask;

class AutomationTaskResource extends Resource
{
    protected static ?string $model = AutomationTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
