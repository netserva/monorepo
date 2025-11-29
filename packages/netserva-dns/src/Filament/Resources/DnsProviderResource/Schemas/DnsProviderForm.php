<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DnsProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Main PowerDNS')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Friendly name for this DNS provider'),

                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'powerdns' => 'PowerDNS',
                        'cloudflare' => 'Cloudflare',
                        'route53' => 'AWS Route53',
                        'digitalocean' => 'DigitalOcean DNS',
                        'linode' => 'Linode DNS',
                        'hetzner' => 'Hetzner DNS',
                        'custom' => 'Custom Provider',
                    ])
                    ->default('powerdns')
                    ->live()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('DNS provider type'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Primary PowerDNS instance for homelab split-horizon DNS'),

            // Connection fields
            Forms\Components\TextInput::make('connection_config.api_endpoint')
                ->label('API Endpoint')
                ->placeholder('http://192.168.1.1:8081')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('PowerDNS API endpoint or provider API URL')
                ->visible(fn (Get $get) => in_array($get('type'), ['powerdns', 'custom'])),

            Forms\Components\TextInput::make('connection_config.api_key')
                ->label('API Key')
                ->password()
                ->revealable()
                ->placeholder('your-api-key-here')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('API authentication key'),

            Forms\Components\TextInput::make('connection_config.api_secret')
                ->label('API Secret')
                ->password()
                ->revealable()
                ->placeholder('your-api-secret-here')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('API secret (for providers that require both key and secret)')
                ->visible(fn (Get $get) => in_array($get('type'), ['cloudflare', 'route53'])),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('connection_config.ssh_host')
                    ->label('SSH Host')
                    ->placeholder('ns1.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('SSH host for tunnel access (optional)'),

                Forms\Components\TextInput::make('connection_config.api_port')
                    ->label('API Port')
                    ->numeric()
                    ->default(8081)
                    ->placeholder('8081')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('PowerDNS API port (default: 8081)'),
            ])->visible(fn (Get $get) => $get('type') === 'powerdns'),

            // Cloudflare specific
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('connection_config.email')
                    ->label('Account Email')
                    ->email()
                    ->placeholder('admin@example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Cloudflare account email'),

                Forms\Components\TextInput::make('connection_config.zone_id')
                    ->label('Zone ID')
                    ->placeholder('abc123def456...')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Cloudflare Zone ID (optional)'),
            ])->visible(fn (Get $get) => $get('type') === 'cloudflare'),

            // AWS Route53 specific
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('connection_config.access_key_id')
                    ->label('Access Key ID')
                    ->placeholder('AKIAIOSFODNN7EXAMPLE')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('AWS Access Key ID'),

                Forms\Components\TextInput::make('connection_config.region')
                    ->label('AWS Region')
                    ->default('us-east-1')
                    ->placeholder('us-east-1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('AWS Region for Route53'),
            ])->visible(fn (Get $get) => $get('type') === 'route53'),

            Forms\Components\TextInput::make('connection_config.secret_access_key')
                ->label('Secret Access Key')
                ->password()
                ->revealable()
                ->placeholder('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('AWS Secret Access Key')
                ->visible(fn (Get $get) => $get('type') === 'route53'),

            // Settings fields
            Grid::make(4)->schema([
                Forms\Components\TextInput::make('timeout')
                    ->label('Timeout')
                    ->numeric()
                    ->default(30)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('API request timeout in seconds'),

                Forms\Components\TextInput::make('rate_limit')
                    ->label('Rate Limit')
                    ->numeric()
                    ->default(100)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Max API requests per minute'),

                Forms\Components\TextInput::make('version')
                    ->label('Version')
                    ->placeholder('4.8.0')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('PowerDNS or API version'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Priority')
                    ->numeric()
                    ->default(0)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display order (lower first)'),
            ]),

            Forms\Components\Toggle::make('active')
                ->label('Active')
                ->default(true),
        ];
    }
}
