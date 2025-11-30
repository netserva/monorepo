<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\IpamResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use NetServa\Fleet\Models\IpNetwork;

class NetworkForm
{
    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Office LAN, Production Network')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Descriptive name for this network'),

                TextInput::make('cidr')
                    ->label('CIDR Notation')
                    ->required()
                    ->maxLength(43)
                    ->placeholder('e.g., 192.168.1.0/24')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Network address in CIDR notation'),
            ]),

            Grid::make(2)->schema([
                Select::make('network_type')
                    ->label('Type')
                    ->required()
                    ->options(IpNetwork::NETWORK_TYPES)
                    ->default('private'),

                Select::make('ip_version')
                    ->label('IP Version')
                    ->required()
                    ->options([
                        '4' => 'IPv4',
                        '6' => 'IPv6',
                    ])
                    ->default('4'),
            ]),

            Grid::make(2)->schema([
                TextInput::make('gateway')
                    ->maxLength(45)
                    ->placeholder('e.g., 192.168.1.1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Default gateway for this network'),

                TextInput::make('total_addresses')
                    ->label('Total Addresses')
                    ->numeric()
                    ->default(0)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Total usable addresses (auto-calculated from CIDR)'),
            ]),

            Section::make('Advanced Options')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('parent_network_id')
                            ->label('Parent Network')
                            ->relationship('parentNetwork', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('None (top-level network)')
                            ->hintIcon('heroicon-o-question-mark-circle')
                            ->hintIconTooltip('Parent network if this is a subnet'),

                        Select::make('fleet_vnode_id')
                            ->label('VNode')
                            ->relationship('vnode', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a vnode'),
                    ]),

                    TagsInput::make('dns_servers')
                        ->label('DNS Servers')
                        ->placeholder('Add DNS server IPs'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Inactive networks are excluded from allocation'),

                    Textarea::make('description')
                        ->rows(2)
                        ->placeholder('Optional description'),
                ]),
        ];
    }

    public static function make(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }
}
