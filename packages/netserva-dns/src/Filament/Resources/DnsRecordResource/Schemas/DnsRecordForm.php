<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class DnsRecordForm
{
    /**
     * Get the form components array (reusable for Actions and Schemas)
     */
    public static function getComponents(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required()
                ->placeholder('@')
                ->columnSpanFull()
                ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'Use @ for zone apex, or subdomain name'),

            Forms\Components\Textarea::make('content')
                ->label('Content')
                ->required()
                ->rows(2)
                ->columnSpanFull()
                ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'Record value (IP address, hostname, etc.)'),

            Forms\Components\Select::make('type')
                ->label('Type')
                ->required()
                ->options([
                    'A' => 'A',
                    'AAAA' => 'AAAA',
                    'CNAME' => 'CNAME',
                    'MX' => 'MX',
                    'TXT' => 'TXT',
                    'NS' => 'NS',
                    'PTR' => 'PTR',
                    'SRV' => 'SRV',
                    'CAA' => 'CAA',
                    'SOA' => 'SOA',
                ])
                ->default('A')
                ->native(false),

            Forms\Components\TextInput::make('ttl')
                ->label('TTL')
                ->numeric()
                ->default(300)
                ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'Time To Live in seconds'),

            Forms\Components\TextInput::make('priority')
                ->label('Priority')
                ->numeric()
                ->default(0)
                ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'For MX/SRV records'),

            Forms\Components\TextInput::make('comment')
                ->label('Comment')
                ->maxLength(255)
                ->columnSpanFull()
                ->placeholder('Optional comment'),

            Forms\Components\Toggle::make('disabled')
                ->label('Disabled')
                ->default(false),
        ];
    }

    /**
     * Get the components wrapped in a 3-column Grid (for Actions)
     */
    public static function getGridComponents(): array
    {
        return [
            Grid::make(3)->schema(self::getComponents()),
        ];
    }

    /**
     * Configure the Schema (for Resource forms)
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components(self::getComponents());
    }
}
