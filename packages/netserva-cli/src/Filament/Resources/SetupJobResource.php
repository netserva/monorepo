<?php

namespace NetServa\Cli\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Cli\Filament\Resources\SetupJobResource\Pages\CreateSetupJob;
use NetServa\Cli\Filament\Resources\SetupJobResource\Pages\EditSetupJob;
use NetServa\Cli\Filament\Resources\SetupJobResource\Pages\ListSetupJobs;
use NetServa\Cli\Filament\Resources\SetupJobResource\Schemas\SetupJobForm;
use NetServa\Cli\Filament\Resources\SetupJobResource\Tables\SetupJobsTable;
use NetServa\Cli\Models\SetupJob;

class SetupJobResource extends Resource
{
    protected static ?string $model = SetupJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'CLI Management';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return SetupJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SetupJobsTable::configure($table);
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
            'index' => ListSetupJobs::route('/'),
            'create' => CreateSetupJob::route('/create'),
            'edit' => EditSetupJob::route('/{record}/edit'),
        ];
    }
}
