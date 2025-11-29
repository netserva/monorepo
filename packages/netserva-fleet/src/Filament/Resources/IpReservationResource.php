<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Pages\CreateIpReservation;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Pages\EditIpReservation;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Pages\ListIpReservations;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Schemas\IpReservationForm;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Tables\IpReservationsTable;
use NetServa\Fleet\Models\IpReservation;
use UnitEnum;

class IpReservationResource extends Resource
{
    protected static ?string $model = IpReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return IpReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IpReservationsTable::configure($table);
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
            'index' => ListIpReservations::route('/'),
            'create' => CreateIpReservation::route('/create'),
            'edit' => EditIpReservation::route('/{record}/edit'),
        ];
    }
}
