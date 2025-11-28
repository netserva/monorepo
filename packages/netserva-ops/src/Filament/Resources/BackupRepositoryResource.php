<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Clusters\Operations\OperationsCluster;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource\Pages\CreateBackupRepository;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource\Pages\EditBackupRepository;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource\Pages\ListBackupRepositories;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource\Schemas\BackupRepositoryForm;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource\Tables\BackupRepositoriesTable;
use NetServa\Ops\Models\BackupRepository;

class BackupRepositoryResource extends Resource
{
    protected static ?string $model = BackupRepository::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $cluster = OperationsCluster::class;

    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return BackupRepositoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BackupRepositoriesTable::configure($table);
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
            'index' => ListBackupRepositories::route('/'),
            'create' => CreateBackupRepository::route('/create'),
            'edit' => EditBackupRepository::route('/{record}/edit'),
        ];
    }
}
