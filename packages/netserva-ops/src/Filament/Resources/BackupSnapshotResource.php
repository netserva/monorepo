<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource\Pages\CreateBackupSnapshot;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource\Pages\EditBackupSnapshot;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource\Pages\ListBackupSnapshots;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource\Schemas\BackupSnapshotForm;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource\Tables\BackupSnapshotsTable;
use NetServa\Ops\Models\BackupSnapshot;

class BackupSnapshotResource extends Resource
{
    protected static ?string $model = BackupSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Backups';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return BackupSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BackupSnapshotsTable::configure($table);
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
            'index' => ListBackupSnapshots::route('/'),
            'create' => CreateBackupSnapshot::route('/create'),
            'edit' => EditBackupSnapshot::route('/{record}/edit'),
        ];
    }
}
