<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\WebServerResource\Pages\CreateWebServer;
use NetServa\Web\Filament\Resources\WebServerResource\Pages\EditWebServer;
use NetServa\Web\Filament\Resources\WebServerResource\Pages\ListWebServers;
use NetServa\Web\Filament\Resources\WebServerResource\Schemas\WebServerForm;
use NetServa\Web\Filament\Resources\WebServerResource\Tables\WebServersTable;
use NetServa\Web\Models\WebServer;
use UnitEnum;

class WebServerResource extends Resource
{
    protected static ?string $model = WebServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return WebServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebServersTable::configure($table);
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
            'index' => ListWebServers::route('/'),
            'create' => CreateWebServer::route('/create'),
            'edit' => EditWebServer::route('/{record}/edit'),
        ];
    }
}
