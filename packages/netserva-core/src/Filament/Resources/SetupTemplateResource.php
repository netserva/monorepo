<?php

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\CreateSetupTemplate;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\EditSetupTemplate;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\ListSetupTemplates;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Schemas\SetupTemplateForm;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Tables\SetupTemplatesTable;
use NetServa\Core\Models\SetupTemplate;
use UnitEnum;

class SetupTemplateResource extends Resource
{
    protected static ?string $model = SetupTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static UnitEnum|string|null $navigationGroup = 'Cli';

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
