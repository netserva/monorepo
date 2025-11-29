<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use NetServa\Core\Filament\Resources\MigrationJobResource\Pages\CreateMigrationJob;
use NetServa\Core\Filament\Resources\MigrationJobResource\Pages\EditMigrationJob;
use NetServa\Core\Filament\Resources\MigrationJobResource\Pages\ListMigrationJobs;
use NetServa\Core\Filament\Resources\MigrationJobResource\Schemas\MigrationJobForm;
use NetServa\Core\Filament\Resources\MigrationJobResource\Tables\MigrationJobsTable;
use NetServa\Core\Models\MigrationJob;
use UnitEnum;

class MigrationJobResource extends Resource
{
    protected static ?string $model = MigrationJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static UnitEnum|string|null $navigationGroup = 'Cli';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MigrationJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MigrationJobsTable::configure($table);
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
            'index' => ListMigrationJobs::route('/'),
            'create' => CreateMigrationJob::route('/create'),
            'edit' => EditMigrationJob::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
