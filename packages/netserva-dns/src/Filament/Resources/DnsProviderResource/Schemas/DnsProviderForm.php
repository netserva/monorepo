<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class DnsProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Provider Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Main PowerDNS')
                            ->helperText('Friendly name for this DNS provider'),

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
                            ->helperText('DNS provider type'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Primary PowerDNS instance for homelab split-horizon DNS'),

                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable this DNS provider'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Connection Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('connection_config.api_endpoint')
                            ->label('API Endpoint')
                            ->placeholder('http://192.168.1.1:8081')
                            ->helperText('PowerDNS API endpoint or provider API URL')
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['powerdns', 'custom']))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('connection_config.api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('your-api-key-here')
                            ->helperText('API authentication key')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('connection_config.api_secret')
                            ->label('API Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('your-api-secret-here')
                            ->helperText('API secret (for providers that require both key and secret)')
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['cloudflare', 'route53']))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('connection_config.ssh_host')
                            ->label('SSH Host')
                            ->placeholder('ns1.example.com')
                            ->helperText('SSH host for tunnel access (optional - for remote PowerDNS)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'powerdns')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('connection_config.api_port')
                            ->label('API Port')
                            ->numeric()
                            ->default(8081)
                            ->placeholder('8081')
                            ->helperText('PowerDNS API port (default: 8081)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'powerdns'),

                        Forms\Components\TextInput::make('timeout')
                            ->label('Timeout (seconds)')
                            ->numeric()
                            ->default(30)
                            ->minValue(5)
                            ->maxValue(300)
                            ->helperText('API request timeout'),

                        Forms\Components\TextInput::make('rate_limit')
                            ->label('Rate Limit (requests/min)')
                            ->numeric()
                            ->default(100)
                            ->minValue(1)
                            ->maxValue(10000)
                            ->helperText('Maximum API requests per minute'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Advanced Settings')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->label('Provider Version')
                            ->placeholder('4.8.0')
                            ->helperText('PowerDNS version or API version'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Display order (lower numbers first)'),

                        Forms\Components\KeyValue::make('sync_config')
                            ->label('Sync Configuration')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->helperText('Additional sync/cache settings')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Provider-Specific Configuration')
                    ->schema([
                        // Cloudflare specific
                        Forms\Components\TextInput::make('connection_config.zone_id')
                            ->label('Zone ID')
                            ->placeholder('abc123def456...')
                            ->helperText('Cloudflare Zone ID (optional - for specific zone operations)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'cloudflare'),

                        Forms\Components\TextInput::make('connection_config.email')
                            ->label('Account Email')
                            ->email()
                            ->placeholder('admin@example.com')
                            ->helperText('Cloudflare account email')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'cloudflare'),

                        // AWS Route53 specific
                        Forms\Components\TextInput::make('connection_config.access_key_id')
                            ->label('Access Key ID')
                            ->placeholder('AKIAIOSFODNN7EXAMPLE')
                            ->helperText('AWS Access Key ID')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'route53'),

                        Forms\Components\TextInput::make('connection_config.secret_access_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->placeholder('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
                            ->helperText('AWS Secret Access Key')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'route53'),

                        Forms\Components\TextInput::make('connection_config.region')
                            ->label('AWS Region')
                            ->default('us-east-1')
                            ->placeholder('us-east-1')
                            ->helperText('AWS Region for Route53')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'route53'),
                    ])
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['cloudflare', 'route53']))
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
