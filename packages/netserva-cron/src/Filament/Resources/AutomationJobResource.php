<?php

namespace NetServa\Cron\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Cron\Filament\Resources\AutomationJobResource\Pages\CreateAutomationJob;
use NetServa\Cron\Filament\Resources\AutomationJobResource\Pages\EditAutomationJob;
use NetServa\Cron\Filament\Resources\AutomationJobResource\Pages\ListAutomationJobs;
use NetServa\Cron\Filament\Resources\AutomationJobResource\Schemas\AutomationJobForm;
use NetServa\Cron\Filament\Resources\AutomationJobResource\Tables\AutomationJobsTable;
use NetServa\Cron\Models\AutomationJob;

class AutomationJobResource extends Resource
{
    protected static ?string $model = AutomationJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 10;

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
