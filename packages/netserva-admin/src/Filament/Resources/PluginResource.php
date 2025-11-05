<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use NetServa\Admin\Filament\Resources\PluginResource\Pages;
use NetServa\Admin\Filament\Resources\PluginResource\Schemas\PluginForm;
use NetServa\Admin\Filament\Resources\PluginResource\Tables\PluginsTable;
use NetServa\Core\Models\InstalledPlugin;

class PluginResource extends Resource
{
    protected static ?string $model = InstalledPlugin::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Plugins';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return config('netserva-admin.navigation_group', 'Administration');
    }

    public static function form(Schema $schema): Schema
    {
        return PluginForm::make($schema);
    }

    public static function table(Table $table): Table
    {
        return PluginsTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlugins::route('/'),
            'view' => Pages\ViewPlugin::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Plugins are discovered automatically
    }
}
