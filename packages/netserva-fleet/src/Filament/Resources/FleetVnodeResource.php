<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Clusters\Fleet\FleetCluster;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource\Pages;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\FleetDiscoveryService;

/**
 * Fleet VNode Resource
 *
 * Manages servers in the fleet hierarchy
 */
class FleetVnodeResource extends Resource
{
    protected static ?string $model = FleetVnode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?string $navigationLabel = 'VNodes';

    protected static ?string $modelLabel = 'VNode';

    protected static ?string $pluralModelLabel = 'VNodes';

    protected static ?string $cluster = FleetCluster::class;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Server identifier (e.g., mgo, nsorg, haproxy)'),

                        Forms\Components\TextInput::make('slug')
                            ->helperText('Auto-generated from name if left empty'),

                        Forms\Components\Select::make('vsite_id')
                            ->relationship('vsite', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Hosting provider/location'),

                        Forms\Components\Select::make('ssh_host_id')
                            ->relationship('sshHost', 'host')
                            ->searchable()
                            ->preload()
                            ->helperText('SSH configuration for this server'),

                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Classification')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->required()
                            ->options([
                                'compute' => 'Compute Node',
                                'network' => 'Network Node',
                                'storage' => 'Storage Node',
                                'mixed' => 'Mixed Role',
                            ])
                            ->default('compute')
                            ->helperText('Primary role of this server'),

                        Forms\Components\Select::make('environment')
                            ->required()
                            ->options([
                                'development' => 'Development',
                                'staging' => 'Staging',
                                'production' => 'Production',
                            ])
                            ->default('production')
                            ->helperText('Environment classification'),

                        Forms\Components\Select::make('discovery_method')
                            ->required()
                            ->options([
                                'ssh' => 'SSH Discovery',
                                'api' => 'API Discovery',
                                'manual' => 'Manual Entry',
                            ])
                            ->default('ssh')
                            ->helperText('How system information is discovered'),
                    ])
                    ->columns(3),

                Section::make('System Information')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')
                            ->helperText('Primary IP address'),

                        Forms\Components\TextInput::make('operating_system')
                            ->helperText('OS information'),

                        Forms\Components\TextInput::make('kernel_version')
                            ->helperText('Kernel version'),

                        Forms\Components\TextInput::make('cpu_cores')
                            ->numeric()
                            ->helperText('Number of CPU cores'),

                        Forms\Components\TextInput::make('memory_mb')
                            ->numeric()
                            ->helperText('Memory in MB'),

                        Forms\Components\TextInput::make('disk_gb')
                            ->numeric()
                            ->helperText('Disk space in GB'),
                    ])
                    ->columns(3),

                Section::make('Discovery Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('scan_frequency_hours')
                            ->numeric()
                            ->default(24)
                            ->helperText('Hours between discovery scans'),

                        Forms\Components\DateTimePicker::make('next_scan_at')
                            ->helperText('Next scheduled discovery'),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Maintenance',
                                'error' => 'Error',
                            ])
                            ->default('active'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Enable/disable this VNode'),
                    ])
                    ->columns(2),

                Section::make('Discovery Status')
                    ->schema([
                        Forms\Components\DateTimePicker::make('last_discovered_at')
                            ->disabled()
                            ->helperText('Last successful discovery'),

                        Forms\Components\Textarea::make('last_error')
                            ->disabled()
                            ->helperText('Last discovery error')
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('vsite.name')
                    ->label('VSite')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'compute' => 'success',
                        'network' => 'info',
                        'storage' => 'warning',
                        'mixed' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('environment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'production' => 'danger',
                        'staging' => 'warning',
                        'development' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('vhost_count')
                    ->label('VHosts')
                    ->getStateUsing(fn (FleetVnode $record) => $record->vhosts()->count())
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('system_summary')
                    ->label('System')
                    ->getStateUsing(function (FleetVnode $record): string {
                        $parts = [];
                        if ($record->cpu_cores) {
                            $parts[] = "{$record->cpu_cores}C";
                        }
                        if ($record->memory_gb) {
                            $parts[] = "{$record->memory_gb}GB";
                        }

                        return implode(' / ', $parts) ?: 'Unknown';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('discovery_status')
                    ->label('Discovery')
                    ->getStateUsing(fn (FleetVnode $record) => $record->getLastDiscoveryStatus())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'error' => 'danger',
                        'stale' => 'warning',
                        'never' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_discovered_at')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vsite')
                    ->relationship('vsite', 'name'),

                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'compute' => 'Compute',
                        'network' => 'Network',
                        'storage' => 'Storage',
                        'mixed' => 'Mixed',
                    ]),

                Tables\Filters\SelectFilter::make('environment')
                    ->options([
                        'development' => 'Development',
                        'staging' => 'Staging',
                        'production' => 'Production',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                        'error' => 'Error',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),

                Tables\Filters\Filter::make('needs_scanning')
                    ->query(fn ($query) => $query->needsScanning())
                    ->label('Needs Scanning'),
            ])
            ->actions([
                Action::make('discover')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->action(function (FleetVnode $record) {
                        $discoveryService = app(FleetDiscoveryService::class);
                        $success = $discoveryService->discoverVNode($record);

                        if ($success) {
                            Notification::make()
                                ->title('Discovery Successful')
                                ->body("VNode {$record->name} has been discovered successfully.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Discovery Failed')
                                ->body("Failed to discover VNode {$record->name}: {$record->last_error}")
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalDescription('This will run SSH discovery on the selected VNode.'),

                Action::make('test_ssh')
                    ->icon('heroicon-o-signal')
                    ->color('warning')
                    ->action(function (FleetVnode $record) {
                        $discoveryService = app(FleetDiscoveryService::class);
                        $result = $discoveryService->testSshConnection($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('SSH Test Successful')
                                ->body("SSH connection to {$record->name} is working.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('SSH Test Failed')
                                ->body("SSH connection failed: {$result['error']}")
                                ->danger()
                                ->send();
                        }
                    }),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('discover_selected')
                        ->label('Discover Selected')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('info')
                        ->action(function ($records) {
                            $discoveryService = app(FleetDiscoveryService::class);
                            $successful = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($discoveryService->discoverVNode($record)) {
                                    $successful++;
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Discovery Complete')
                                ->body("Discovered {$successful} VNodes successfully, {$failed} failed.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('VNode Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('vsite.name')
                            ->label('VSite'),

                        Infolists\Components\TextEntry::make('sshHost.hostname')
                            ->label('SSH Host'),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Classification')
                    ->schema([
                        Infolists\Components\TextEntry::make('role')
                            ->badge(),

                        Infolists\Components\TextEntry::make('environment')
                            ->badge(),

                        Infolists\Components\TextEntry::make('discovery_method')
                            ->badge(),
                    ])
                    ->columns(3),

                Section::make('System Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('ip_address'),

                        Infolists\Components\TextEntry::make('operating_system'),

                        Infolists\Components\TextEntry::make('kernel_version'),

                        Infolists\Components\TextEntry::make('cpu_cores')
                            ->suffix(' cores'),

                        Infolists\Components\TextEntry::make('memory_mb')
                            ->formatStateUsing(fn ($state) => $state ? round($state / 1024, 1).' GB' : null),

                        Infolists\Components\TextEntry::make('disk_gb')
                            ->suffix(' GB'),
                    ])
                    ->columns(3),

                Section::make('Discovery Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_discovered_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('next_scan_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('scan_frequency_hours')
                            ->suffix(' hours'),

                        Infolists\Components\TextEntry::make('last_error')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFleetVnodes::route('/'),
            'view' => Pages\ViewFleetVnode::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
