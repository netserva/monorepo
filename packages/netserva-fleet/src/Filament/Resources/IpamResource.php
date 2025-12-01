<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpamResource\Pages\ListIpam;
use NetServa\Fleet\Filament\Resources\IpamResource\Pages\ManageAddresses;
use NetServa\Fleet\Filament\Resources\IpamResource\Pages\ManageReservations;
use NetServa\Fleet\Filament\Resources\IpamResource\Schemas\NetworkForm;
use NetServa\Fleet\Filament\Resources\IpamResource\Tables\IpamTable;
use NetServa\Fleet\Models\IpNetwork;
use UnitEnum;

/**
 * Unified IPAM Resource
 *
 * Manages IP networks with modal-based access to addresses and reservations.
 * Consolidates IpNetwork, IpAddress, and IpReservation management.
 */
class IpamResource extends Resource
{
    protected static ?string $model = IpNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'IPAM';

    protected static ?string $modelLabel = 'Network';

    protected static ?string $pluralModelLabel = 'Networks';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return NetworkForm::make($schema);
    }

    public static function table(Table $table): Table
    {
        return IpamTable::make($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIpam::route('/'),
            'addresses' => ManageAddresses::route('/{record}/addresses'),
            'reservations' => ManageReservations::route('/{record}/reservations'),
        ];
    }
}
