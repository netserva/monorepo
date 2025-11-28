<?php

namespace NetServa\Fleet\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Clusters\Fleet\FleetCluster;
use NetServa\Fleet\Filament\Resources\FleetVenueResource\Pages;
use NetServa\Fleet\Models\FleetVenue;

/**
 * Fleet Venue Resource
 *
 * Manages geographic/logical locations in the fleet hierarchy
 */
class FleetVenueResource extends Resource
{
    protected static ?string $model = FleetVenue::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Venues';

    protected static ?string $modelLabel = 'Venue';

    protected static ?string $pluralModelLabel = 'Venues';

    protected static ?string $cluster = FleetCluster::class;

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique identifier for this venue (e.g., homelab, binarylane)'),

                        Forms\Components\TextInput::make('slug')
                            ->helperText('Auto-generated from name if left empty'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Location & Provider')
                    ->schema([
                        Forms\Components\Select::make('provider')
                            ->required()
                            ->options([
                                'homelab' => 'Home Lab',
                                'local' => 'Local Infrastructure',
                                'binarylane' => 'BinaryLane',
                                'aws' => 'Amazon AWS',
                                'azure' => 'Microsoft Azure',
                                'gcp' => 'Google Cloud Platform',
                                'digitalocean' => 'DigitalOcean',
                                'linode' => 'Linode/Akamai',
                                'hetzner' => 'Hetzner',
                                'vultr' => 'Vultr',
                                'customer' => 'Customer Infrastructure',
                                'other' => 'Other Provider',
                            ])
                            ->searchable()
                            ->helperText('Infrastructure provider'),

                        Forms\Components\TextInput::make('location')
                            ->helperText('Geographic location (e.g., Sydney, Australia)'),

                        Forms\Components\TextInput::make('region')
                            ->helperText('Region code or identifier (e.g., ap-southeast-2)'),
                    ])
                    ->columns(3),

                Section::make('DNS Configuration')
                    ->schema([
                        Forms\Components\Select::make('dns_provider_id')
                            ->label('DNS Provider')
                            ->relationship('dnsProvider', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('PowerDNS or Cloudflare provider for this venue'),
                    ])
                    ->columns(1),

                Section::make('Credentials & Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('credentials')
                            ->helperText('API credentials (encrypted storage)')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('metadata')
                            ->helperText('Additional metadata for this venue')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Enable/disable this venue'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'homelab', 'local' => 'success',
                        'binarylane', 'vultr', 'hetzner' => 'info',
                        'aws', 'azure', 'gcp' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('vsite_count')
                    ->label('VSites')
                    ->getStateUsing(fn (FleetVenue $record) => $record->vsites()->count())
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vnode_count')
                    ->label('VNodes')
                    ->getStateUsing(fn (FleetVenue $record) => $record->vnodes()->count())
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'homelab' => 'Home Lab',
                        'local' => 'Local',
                        'binarylane' => 'BinaryLane',
                        'aws' => 'AWS',
                        'azure' => 'Azure',
                        'gcp' => 'GCP',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All venues')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Venue Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('slug'),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Location Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('provider')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('location'),

                        Infolists\Components\TextEntry::make('region'),

                        Infolists\Components\TextEntry::make('dnsProvider.name')
                            ->label('DNS Provider'),
                    ])
                    ->columns(2),

                Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('vsite_count')
                            ->label('VSites')
                            ->getStateUsing(fn (FleetVenue $record) => $record->vsites()->count()),

                        Infolists\Components\TextEntry::make('vnode_count')
                            ->label('VNodes')
                            ->getStateUsing(fn (FleetVenue $record) => $record->vnodes()->count()),

                        Infolists\Components\TextEntry::make('vhost_count')
                            ->label('VHosts')
                            ->getStateUsing(fn (FleetVenue $record) => $record->vhosts()->count()),

                        Infolists\Components\IconEntry::make('is_active')
                            ->boolean(),
                    ])
                    ->columns(4),

                Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFleetVenues::route('/'),
            'view' => Pages\ViewFleetVenue::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
