<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource\Pages;
use NetServa\Fleet\Models\FleetVsite;
use UnitEnum;

/**
 * Fleet VSite Resource
 *
 * Manages hosting providers/locations in the fleet hierarchy
 */
class FleetVsiteResource extends Resource
{
    protected static ?string $model = FleetVsite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'VSites';

    protected static ?string $modelLabel = 'VSite';

    protected static ?string $pluralModelLabel = 'VSites';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 7;  // Alphabetical: VSites

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique identifier for this VSite (e.g., local-incus, binarylane-sydney)'),

                        Forms\Components\TextInput::make('slug')
                            ->helperText('Auto-generated from name if left empty'),

                        Forms\Components\TextInput::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Provider Configuration')
                    ->schema([
                        Forms\Components\Select::make('provider')
                            ->required()
                            ->options([
                                'local' => 'Local Infrastructure',
                                'binarylane' => 'BinaryLane',
                                'customer' => 'Customer Infrastructure',
                                'other' => 'Other Provider',
                            ])
                            ->helperText('Infrastructure provider or location'),

                        Forms\Components\Select::make('technology')
                            ->required()
                            ->options([
                                'incus' => 'Incus/LXD',
                                'proxmox' => 'Proxmox VE',
                                'vps' => 'VPS',
                                'hardware' => 'Bare Metal',
                                'router' => 'Network Router',
                            ])
                            ->helperText('Technology platform'),

                        Forms\Components\TextInput::make('location')
                            ->helperText('Geographic location or site identifier'),
                    ])
                    ->columns(3),

                Section::make('API Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('api_endpoint')
                            ->url()
                            ->helperText('API endpoint URL for programmatic access'),

                        Forms\Components\KeyValue::make('api_credentials')
                            ->helperText('API credentials (encrypted storage)')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Capabilities & Status')
                    ->schema([
                        Forms\Components\TagsInput::make('capabilities')
                            ->helperText('Features available on this VSite'),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Maintenance',
                            ])
                            ->default('active'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Enable/disable this VSite'),
                    ])
                    ->columns(3),
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

                Tables\Columns\TextColumn::make('provider_tech')
                    ->label('Provider/Tech')
                    ->getStateUsing(fn (FleetVsite $record) => $record->getProviderTech())
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'Local') => 'success',
                        str_contains($state, 'BinaryLane') => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('location')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('node_count')
                    ->label('Nodes')
                    ->getStateUsing(fn (FleetVsite $record) => $record->vnodes()->count())
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vhost_count')
                    ->label('VHosts')
                    ->getStateUsing(fn (FleetVsite $record) => $record->vhosts()->count())
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

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
                        'local' => 'Local',
                        'binarylane' => 'BinaryLane',
                        'customer' => 'Customer',
                    ]),

                Tables\Filters\SelectFilter::make('technology')
                    ->options([
                        'incus' => 'Incus',
                        'proxmox' => 'Proxmox',
                        'vps' => 'VPS',
                        'hardware' => 'Hardware',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),
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
                Section::make('VSite Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('slug'),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Provider Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('provider')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('technology')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('location'),

                        Infolists\Components\TextEntry::make('api_endpoint')
                            ->url(fn (?string $state): ?string => $state),
                    ])
                    ->columns(2),

                Section::make('Capabilities')
                    ->schema([
                        Infolists\Components\TextEntry::make('capabilities')
                            ->listWithLineBreaks()
                            ->bulleted(),
                    ]),

                Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('node_count')
                            ->label('VNodes')
                            ->getStateUsing(fn (FleetVsite $record) => $record->vnodes()->count()),

                        Infolists\Components\TextEntry::make('vhost_count')
                            ->label('VHosts')
                            ->getStateUsing(fn (FleetVsite $record) => $record->vhosts()->count()),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'maintenance' => 'warning',
                                'inactive' => 'danger',
                                default => 'gray',
                            }),

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
            'index' => Pages\ManageFleetVsites::route('/'),
            'view' => Pages\ViewFleetVsite::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
