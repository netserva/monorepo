<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\VHostResource\Pages\CreateVHost;
use NetServa\Core\Filament\Resources\VHostResource\Pages\EditVHost;
use NetServa\Core\Filament\Resources\VHostResource\Pages\ListVHosts;
use NetServa\Core\Filament\Resources\VHostResource\Schemas\VHostForm;
use NetServa\Core\Filament\Resources\VHostResource\Tables\VHostsTable;
use NetServa\Core\Models\VHost;

class VHostResource extends Resource
{
    protected static ?string $model = VHost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return VHostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VHostsTable::configure($table);
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
            'index' => ListVHosts::route('/'),
            'create' => CreateVHost::route('/create'),
            'edit' => EditVHost::route('/{record}/edit'),
        ];
    }
}
