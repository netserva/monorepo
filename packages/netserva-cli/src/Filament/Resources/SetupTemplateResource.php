<?php

namespace NetServa\Cli\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Cli\Filament\Resources\SetupTemplateResource\Pages\CreateSetupTemplate;
use NetServa\Cli\Filament\Resources\SetupTemplateResource\Pages\EditSetupTemplate;
use NetServa\Cli\Filament\Resources\SetupTemplateResource\Pages\ListSetupTemplates;
use NetServa\Cli\Filament\Resources\SetupTemplateResource\Schemas\SetupTemplateForm;
use NetServa\Cli\Filament\Resources\SetupTemplateResource\Tables\SetupTemplatesTable;
use NetServa\Cli\Models\SetupTemplate;

class SetupTemplateResource extends Resource
{
    protected static ?string $model = SetupTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'CLI Management';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return SetupTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SetupTemplatesTable::configure($table);
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
            'index' => ListSetupTemplates::route('/'),
            'create' => CreateSetupTemplate::route('/create'),
            'edit' => EditSetupTemplate::route('/{record}/edit'),
        ];
    }
}
