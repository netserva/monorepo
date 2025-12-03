<?php

namespace NetServa\Dns\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages\ListDomainRegistrars;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource\Tables\DomainRegistrarsTable;
use NetServa\Dns\Models\DomainRegistrar;
use UnitEnum;

class DomainRegistrarResource extends Resource
{
    protected static ?string $model = DomainRegistrar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static UnitEnum|string|null $navigationGroup = 'Dns';

    protected static ?int $navigationSort = 40;

    protected static bool $shouldRegisterNavigation = false;

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Namecheap')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display name for this registrar'),

                Forms\Components\Select::make('registrar_type')
                    ->required()
                    ->options([
                        'synergywholesale' => 'SynergyWholesale',
                        'namecheap' => 'Namecheap',
                        'godaddy' => 'GoDaddy',
                        'cloudflare' => 'Cloudflare',
                        'route53' => 'Route53',
                        'other' => 'Other',
                    ])
                    ->default('synergywholesale')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Type of registrar API'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('api_endpoint')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://api.registrar.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('API endpoint URL for this registrar'),

                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'testing' => 'Testing',
                    ])
                    ->default('active')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current status of this registrar connection'),
            ]),

            Forms\Components\TextInput::make('api_key_encrypted')
                ->label('API Key')
                ->password()
                ->maxLength(255)
                ->placeholder('Enter API key')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('API key (will be encrypted)'),

            Forms\Components\TextInput::make('api_secret_encrypted')
                ->label('API Secret')
                ->password()
                ->maxLength(255)
                ->placeholder('Enter API secret')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('API secret (will be encrypted)'),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this registrar'),

            Forms\Components\KeyValue::make('additional_config')
                ->label('Additional Configuration')
                ->keyLabel('Setting')
                ->valueLabel('Value')
                ->addActionLabel('Add setting')
                ->reorderable(false)
                ->default([]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return DomainRegistrarsTable::configure($table);
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
            'index' => ListDomainRegistrars::route('/'),
        ];
    }
}
