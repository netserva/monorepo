<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Clusters\Web\WebCluster;
use NetServa\Web\Filament\Resources\VirtualHostResource\Pages\CreateVirtualHost;
use NetServa\Web\Filament\Resources\VirtualHostResource\Pages\EditVirtualHost;
use NetServa\Web\Filament\Resources\VirtualHostResource\Pages\ListVirtualHosts;
use NetServa\Web\Filament\Resources\VirtualHostResource\Schemas\VirtualHostForm;
use NetServa\Web\Filament\Resources\VirtualHostResource\Tables\VirtualHostsTable;
use NetServa\Web\Models\VirtualHost;

class VirtualHostResource extends Resource
{
    protected static ?string $model = VirtualHost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $cluster = WebCluster::class;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return VirtualHostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VirtualHostsTable::configure($table);
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
            'index' => ListVirtualHosts::route('/'),
            'create' => CreateVirtualHost::route('/create'),
            'edit' => EditVirtualHost::route('/{record}/edit'),
        ];
    }
}
