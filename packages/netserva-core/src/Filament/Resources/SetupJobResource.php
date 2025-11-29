<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SetupJobResource\Pages\CreateSetupJob;
use NetServa\Core\Filament\Resources\SetupJobResource\Pages\EditSetupJob;
use NetServa\Core\Filament\Resources\SetupJobResource\Pages\ListSetupJobs;
use NetServa\Core\Filament\Resources\SetupJobResource\Schemas\SetupJobForm;
use NetServa\Core\Filament\Resources\SetupJobResource\Tables\SetupJobsTable;
use NetServa\Core\Models\SetupJob;
use UnitEnum;

class SetupJobResource extends Resource
{
    protected static ?string $model = SetupJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static UnitEnum|string|null $navigationGroup = 'Cli';

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
