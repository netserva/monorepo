<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\BackupJobResource\Pages\CreateBackupJob;
use NetServa\Ops\Filament\Resources\BackupJobResource\Pages\EditBackupJob;
use NetServa\Ops\Filament\Resources\BackupJobResource\Pages\ListBackupJobs;
use NetServa\Ops\Filament\Resources\BackupJobResource\Schemas\BackupJobForm;
use NetServa\Ops\Filament\Resources\BackupJobResource\Tables\BackupJobsTable;
use NetServa\Ops\Models\BackupJob;

class BackupJobResource extends Resource
{
    protected static ?string $model = BackupJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BackupJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BackupJobsTable::configure($table);
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
            'index' => ListBackupJobs::route('/'),
            'create' => CreateBackupJob::route('/create'),
            'edit' => EditBackupJob::route('/{record}/edit'),
        ];
    }
}
