<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\WireguardResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use NetServa\Fleet\Models\FleetVnode;

class ServerForm
{
    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('wg0')
                    ->hintIcon('heroicon-o-question-mark-circle', 'Unique name for this WireGuard interface (e.g., wg0, wg-office)'),

                TextInput::make('server_ip')
                    ->required()
                    ->label('Server IP')
                    ->placeholder('10.200.0.1')
                    ->rules(['ip'])
                    ->hintIcon('heroicon-o-question-mark-circle', 'The server\'s IP address within the VPN network'),

                TextInput::make('network_cidr')
                    ->required()
                    ->label('Network CIDR')
                    ->placeholder('10.200.0.0/24')
                    ->rules(['regex:/^\d+\.\d+\.\d+\.\d+\/\d+$/'])
                    ->hintIcon('heroicon-o-question-mark-circle', 'VPN network range in CIDR notation (e.g., 10.200.0.0/24 for 254 peers)'),

                TextInput::make('listen_port')
                    ->required()
                    ->numeric()
                    ->default(51820)
                    ->minValue(1)
                    ->maxValue(65535)
                    ->hintIcon('heroicon-o-question-mark-circle', 'UDP port for incoming connections (default: 51820)'),

                TextInput::make('endpoint')
                    ->placeholder('vpn.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle', 'Public hostname or IP for clients to connect'),

                Select::make('fleet_vnode_id')
                    ->label('Host Vnode')
                    ->options(FleetVnode::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select host server')
                    ->hintIcon('heroicon-o-question-mark-circle', 'The server/vnode where this WireGuard interface runs'),
            ]),

            TextInput::make('public_key')
                ->label('Public Key')
                ->placeholder('Auto-generated on save')
                ->maxLength(44)
                ->hintIcon('heroicon-o-question-mark-circle', 'Server\'s Curve25519 public key (auto-generated if left empty)'),

            Textarea::make('description')
                ->placeholder('Notes about this WireGuard server')
                ->rows(2)
                ->hintIcon('heroicon-o-question-mark-circle', 'Optional notes or documentation for this VPN server'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->hintIcon('heroicon-o-question-mark-circle', 'Inactive servers are not deployed to hosts'),
        ];
    }
}
