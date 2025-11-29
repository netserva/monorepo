<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\IncidentResource\Pages\CreateIncident;
use NetServa\Ops\Filament\Resources\IncidentResource\Pages\EditIncident;
use NetServa\Ops\Filament\Resources\IncidentResource\Pages\ListIncidents;
use NetServa\Ops\Filament\Resources\IncidentResource\Schemas\IncidentForm;
use NetServa\Ops\Filament\Resources\IncidentResource\Tables\IncidentsTable;
use NetServa\Ops\Models\Incident;
use UnitEnum;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return IncidentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncidentsTable::configure($table);
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
            'index' => ListIncidents::route('/'),
            'create' => CreateIncident::route('/create'),
            'edit' => EditIncident::route('/{record}/edit'),
        ];
    }
}
