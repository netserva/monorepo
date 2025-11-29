<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SshHostResource\Pages;
use NetServa\Core\Filament\Resources\SshHostResource\Schemas\SshHostForm;
use NetServa\Core\Filament\Resources\SshHostResource\Tables\SshHostsTable;
use NetServa\Core\Models\SshHost;

class SshHostResource extends Resource
{
    protected static ?string $model = SshHost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?string $navigationLabel = 'SSH Hosts';

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'host';

    public static function form(Schema $schema): Schema
    {
        return SshHostForm::make($schema);
    }

    public static function table(Table $table): Table
    {
        return SshHostsTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSshHosts::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['host', 'hostname', 'description'];
    }
}
