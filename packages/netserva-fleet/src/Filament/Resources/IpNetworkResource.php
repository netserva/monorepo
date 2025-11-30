<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Pages\ListIpNetworks;
use NetServa\Fleet\Filament\Resources\IpNetworkResource\Tables\IpNetworksTable;
use NetServa\Fleet\Models\IpNetwork;
use UnitEnum;

class IpNetworkResource extends Resource
{
    protected static ?string $model = IpNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 2;  // Alphabetical: Ip Networks

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Production Network')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Descriptive name for this network'),

                Forms\Components\TextInput::make('cidr')
                    ->label('CIDR Notation')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., 192.168.1.0/24 or 2001:db8::/32')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Network address in CIDR notation'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('network_type')
                    ->required()
                    ->options(IpNetwork::NETWORK_TYPES)
                    ->default('private')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Classification of this network'),

                Forms\Components\Select::make('ip_version')
                    ->label('IP Version')
                    ->required()
                    ->options([
                        '4' => 'IPv4',
                        '6' => 'IPv6',
                    ])
                    ->default('4')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('IP protocol version'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('gateway')
                    ->maxLength(255)
                    ->placeholder('e.g., 192.168.1.1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Default gateway for this network'),

                Forms\Components\Select::make('fleet_vnode_id')
                    ->label('VNode')
                    ->relationship('vnode', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Select a vnode')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Associated virtual node'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('parent_network_id')
                    ->label('Parent Network')
                    ->relationship('parentNetwork', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('None (top-level network)')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Parent network if this is a subnet'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Enable or disable this network'),
            ]),

            Forms\Components\TagsInput::make('dns_servers')
                ->label('DNS Servers')
                ->placeholder('Add DNS server IPs')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('DNS servers for this network (press Enter to add)'),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this network'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return IpNetworksTable::configure($table);
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
            'index' => ListIpNetworks::route('/'),
        ];
    }
}
