<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class DnsZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('dns_provider_id')
                    ->label('DNS Provider')
                    ->required()
                    ->relationship('dnsProvider', 'name')
                    ->searchable()
                    ->preload()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Select the DNS provider for this zone'),

                Forms\Components\TextInput::make('name')
                    ->label('Zone Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Fully qualified domain name (automatically adds trailing dot if needed)'),
            ]),

            Grid::make(3)->schema([
                Forms\Components\Select::make('kind')
                    ->label('Zone Type')
                    ->required()
                    ->options([
                        'Native' => 'Native',
                        'Primary' => 'Primary',
                        'Master' => 'Master',
                        'Secondary' => 'Secondary',
                        'Slave' => 'Slave',
                    ])
                    ->default('Primary')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Zone replication type'),

                Forms\Components\TextInput::make('ttl')
                    ->label('Default TTL')
                    ->numeric()
                    ->default(3600)
                    ->suffix('seconds')
                    ->placeholder('3600')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Default Time-To-Live for records in this zone'),

                Forms\Components\Toggle::make('active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Enable or disable this zone'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Toggle::make('dnssec_enabled')
                    ->label('DNSSEC Enabled')
                    ->default(false)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Enable DNSSEC for this zone'),

                Forms\Components\Toggle::make('auto_dnssec')
                    ->label('Auto DNSSEC')
                    ->default(false)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Automatically manage DNSSEC keys'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(65535)
                ->placeholder('Optional description for this zone'),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('account')
                    ->maxLength(255)
                    ->placeholder('Optional account identifier'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->placeholder('0')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display order (lower numbers appear first)'),
            ]),
        ];
    }
}
