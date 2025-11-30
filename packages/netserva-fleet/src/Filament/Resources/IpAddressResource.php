<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Pages\ListIpAddresses;
use NetServa\Fleet\Filament\Resources\IpAddressResource\Tables\IpAddressesTable;
use NetServa\Fleet\Models\IpAddress;
use UnitEnum;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 1;  // Alphabetical: Ip Addresses

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('ip_network_id')
                    ->label('IP Network')
                    ->relationship('ipNetwork', 'network_address')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('The network this IP address belongs to'),

                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->required()
                    ->maxLength(45)
                    ->placeholder('192.168.1.10')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('IPv4 or IPv6 address'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('hostname')
                    ->maxLength(255)
                    ->placeholder('server1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Short hostname for this IP'),

                Forms\Components\TextInput::make('fqdn')
                    ->label('FQDN')
                    ->maxLength(255)
                    ->placeholder('server1.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Fully Qualified Domain Name'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('status')
                    ->required()
                    ->options(IpAddress::STATUSES)
                    ->default('available')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current allocation status of this IP'),

                Forms\Components\TextInput::make('mac_address')
                    ->label('MAC Address')
                    ->maxLength(17)
                    ->placeholder('00:11:22:33:44:55')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Hardware address (if known)'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('owner')
                    ->maxLength(255)
                    ->placeholder('John Doe')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Person or entity responsible for this IP'),

                Forms\Components\TextInput::make('service')
                    ->maxLength(255)
                    ->placeholder('Web Server')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Service or application using this IP'),
            ]),

            Forms\Components\Select::make('fleet_vnode_id')
                ->label('VNode')
                ->relationship('vnode', 'name')
                ->searchable()
                ->preload()
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Associated virtual node (if applicable)'),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(65535)
                ->placeholder('Optional notes about this IP address'),

            Forms\Components\DateTimePicker::make('allocated_at')
                ->label('Allocated At')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('When this IP was allocated'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return IpAddressesTable::configure($table);
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
            'index' => ListIpAddresses::route('/'),
        ];
    }
}
