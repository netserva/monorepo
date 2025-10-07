<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\WebApplicationResource\Pages\CreateWebApplication;
use NetServa\Web\Filament\Resources\WebApplicationResource\Pages\EditWebApplication;
use NetServa\Web\Filament\Resources\WebApplicationResource\Pages\ListWebApplications;
use NetServa\Web\Filament\Resources\WebApplicationResource\Schemas\WebApplicationForm;
use NetServa\Web\Filament\Resources\WebApplicationResource\Tables\WebApplicationsTable;
use NetServa\Web\Models\WebApplication;

class WebApplicationResource extends Resource
{
    protected static ?string $model = WebApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WebApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebApplicationsTable::configure($table);
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
            'index' => ListWebApplications::route('/'),
            'create' => CreateWebApplication::route('/create'),
            'edit' => EditWebApplication::route('/{record}/edit'),
        ];
    }
}
