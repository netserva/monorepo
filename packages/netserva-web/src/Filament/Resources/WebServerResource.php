<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\WebServerResource\Pages\ListWebServers;
use NetServa\Web\Filament\Resources\WebServerResource\Schemas\WebServerForm;
use NetServa\Web\Filament\Resources\WebServerResource\Tables\WebServersTable;
use NetServa\Web\Models\WebServer;
use UnitEnum;

class WebServerResource extends Resource
{
    protected static ?string $model = WebServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 2;

    public static function getFormSchema(): array
    {
        return WebServerForm::getSchema();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
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
        ];
    }
}
