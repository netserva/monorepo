<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Pages\ListIpReservations;
use NetServa\Fleet\Filament\Resources\IpReservationResource\Tables\IpReservationsTable;
use NetServa\Fleet\Models\IpReservation;
use UnitEnum;

class IpReservationResource extends Resource
{
    protected static ?string $model = IpReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 3;  // Alphabetical: Ip Reservations

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('ip_network_id')
                    ->label('IP Network')
                    ->required()
                    ->relationship('ipNetwork', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Select network')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('The IP network this reservation belongs to'),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., DHCP Pool')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Descriptive name for this reservation'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('start_ip')
                    ->label('Start IP')
                    ->required()
                    ->placeholder('e.g., 192.168.1.100')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('First IP address in the reservation range'),

                Forms\Components\TextInput::make('end_ip')
                    ->label('End IP')
                    ->required()
                    ->placeholder('e.g., 192.168.1.200')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Last IP address in the reservation range'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('reservation_type')
                    ->required()
                    ->options(IpReservation::RESERVATION_TYPES)
                    ->default('static_range')
                    ->placeholder('Select type')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Type of IP reservation'),

                Forms\Components\TextInput::make('purpose')
                    ->maxLength(255)
                    ->placeholder('e.g., DHCP, Static hosts')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Purpose or use case for this reservation'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional additional notes about this reservation'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->inline(false)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Whether this reservation is currently active'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
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
        ];
    }
}
