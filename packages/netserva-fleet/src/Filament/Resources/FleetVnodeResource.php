<?php

namespace NetServa\Fleet\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use NetServa\Fleet\Filament\Resources\FleetVnodeResource\Pages;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\BinaryLaneService;
use NetServa\Fleet\Services\FleetDiscoveryService;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 3;

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

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('bl_region')
                    ->label('Region')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'syd' => 'success',
                        'bne' => 'info',
                        'mel' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('system_summary')
                    ->label('Specs')
                    ->getStateUsing(function (FleetVnode $record): string {
                        $parts = [];
                        if ($record->cpu_cores) {
                            $parts[] = "{$record->cpu_cores}C";
                        }
                        if ($record->memory_gb) {
                            $parts[] = "{$record->memory_gb}G";
                        }
                        if ($record->disk_gb) {
                            $parts[] = "{$record->disk_gb}GB";
                        }

                        return implode('/', $parts) ?: '-';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('vhost_count')
                    ->label('VHosts')
                    ->getStateUsing(fn (FleetVnode $record) => $record->vhosts()->count())
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                // Hidden by default - toggleable
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'compute' => 'success',
                        'network' => 'info',
                        'storage' => 'warning',
                        'mixed' => 'purple',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('environment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'production' => 'danger',
                        'staging' => 'warning',
                        'development' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_discovered_at')
                    ->label('Last Scan')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // BinaryLane columns
                Tables\Columns\TextColumn::make('bl_server_id')
                    ->label('BL ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bl_size_slug')
                    ->label('BL Size')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bl_synced_at')
                    ->label('BL Synced')
                    ->dateTime()
                    ->since()
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

                Tables\Filters\TernaryFilter::make('binarylane')
                    ->label('BinaryLane')
                    ->placeholder('All VNodes')
                    ->trueLabel('BinaryLane Only')
                    ->falseLabel('Non-BinaryLane')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('bl_server_id'),
                        false: fn ($query) => $query->whereNull('bl_server_id'),
                    ),
            ])
            ->actions([
                Action::make('discover')
                    ->label('')
                    ->tooltip('Run SSH Discovery')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
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
                    ->label('')
                    ->tooltip('Test SSH Connection')
                    ->icon(Heroicon::OutlinedSignal)
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

                // BinaryLane power actions (only visible for BL VNodes)
                ActionGroup::make([
                    Action::make('bl_power_on')
                        ->label('Power On')
                        ->icon(Heroicon::OutlinedPlay)
                        ->color('success')
                        ->visible(fn (FleetVnode $record) => $record->bl_server_id && $record->status === 'inactive')
                        ->requiresConfirmation()
                        ->action(fn (FleetVnode $record) => static::blPowerAction($record, 'powerOn', 'Power On')),

                    Action::make('bl_reboot')
                        ->label('Reboot')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->visible(fn (FleetVnode $record) => $record->bl_server_id && $record->status === 'active')
                        ->requiresConfirmation()
                        ->action(fn (FleetVnode $record) => static::blPowerAction($record, 'reboot', 'Reboot')),

                    Action::make('bl_shutdown')
                        ->label('Shutdown')
                        ->icon(Heroicon::OutlinedStop)
                        ->color('danger')
                        ->visible(fn (FleetVnode $record) => $record->bl_server_id && $record->status === 'active')
                        ->requiresConfirmation()
                        ->modalHeading('Shutdown Server')
                        ->modalDescription('This will gracefully shutdown the BinaryLane server.')
                        ->action(fn (FleetVnode $record) => static::blPowerAction($record, 'shutdown', 'Shutdown')),

                    Action::make('bl_sync_one')
                        ->label('Sync from BL')
                        ->icon(Heroicon::OutlinedCloudArrowDown)
                        ->color('info')
                        ->visible(fn (FleetVnode $record) => (bool) $record->bl_server_id)
                        ->action(fn (FleetVnode $record) => static::blSyncOne($record)),
                ])
                    ->label('')
                    ->tooltip('BinaryLane Actions')
                    ->icon(Heroicon::OutlinedCloud)
                    ->color('gray')
                    ->visible(fn (FleetVnode $record) => (bool) $record->bl_server_id),

                ViewAction::make()
                    ->label('')
                    ->tooltip('View Details')
                    ->modalWidth('md')
                    ->modalFooterActionsAlignment('end'),
                EditAction::make()
                    ->label('')
                    ->tooltip('Edit VNode'),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Delete VNode'),
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
            ->defaultSort('name')
            ->paginated([5, 10, 25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\TextEntry::make('name')
                    ->label('Name')
                    ->weight('bold'),

                Infolists\Components\TextEntry::make('ip_address')
                    ->label('IP')
                    ->copyable(),

                Infolists\Components\TextEntry::make('system_specs')
                    ->label('Specs')
                    ->getStateUsing(function ($record): string {
                        $parts = [];
                        if ($record->cpu_cores) {
                            $parts[] = "{$record->cpu_cores} CPU";
                        }
                        if ($record->memory_mb) {
                            $parts[] = round($record->memory_mb / 1024, 1).' GB';
                        }
                        if ($record->disk_gb) {
                            $parts[] = "{$record->disk_gb} GB disk";
                        }

                        return implode(' · ', $parts) ?: '-';
                    }),

                Infolists\Components\TextEntry::make('bl_region')
                    ->label('Region')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'syd' => 'success',
                        'bne' => 'info',
                        'mel' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'syd' => 'Sydney',
                        'bne' => 'Brisbane',
                        'mel' => 'Melbourne',
                        default => $state ?? '-',
                    })
                    ->visible(fn ($record) => $record->bl_server_id),

                Infolists\Components\TextEntry::make('vsite.name')
                    ->label('VSite')
                    ->badge()
                    ->color('info'),

                Infolists\Components\TextEntry::make('sshHost.host')
                    ->label('SSH Host'),

                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                Infolists\Components\TextEntry::make('last_discovered_at')
                    ->label('Synced')
                    ->since(),

                Infolists\Components\TextEntry::make('last_error')
                    ->label('Error')
                    ->color('danger')
                    ->visible(fn ($record) => $record->last_error),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFleetVnodes::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    /**
     * Execute a BinaryLane power action and update local VNode status
     */
    protected static function blPowerAction(FleetVnode $record, string $action, string $label): void
    {
        try {
            $token = config('fleet.binarylane.api_token');
            if (! $token) {
                throw new \Exception('BinaryLane API token not configured');
            }

            $service = app(BinaryLaneService::class)->setToken($token);
            $service->$action($record->bl_server_id);

            // Update local status based on action
            $newStatus = match ($action) {
                'powerOn' => 'active',
                'shutdown', 'powerOff' => 'inactive',
                default => $record->status,
            };

            $record->update(['status' => $newStatus]);

            Notification::make()
                ->title("{$label} Initiated")
                ->body("BinaryLane server action submitted. Status updated to {$newStatus}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title("{$label} Failed")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Sync a single VNode from BinaryLane API
     */
    protected static function blSyncOne(FleetVnode $record): void
    {
        try {
            $token = config('fleet.binarylane.api_token');
            if (! $token) {
                throw new \Exception('BinaryLane API token not configured');
            }

            $service = app(BinaryLaneService::class)->setToken($token);
            $server = $service->getServer($record->bl_server_id);

            // Update VNode with latest BL data
            $record->update([
                'ip_address' => $server['ipv4'] ?? $record->ip_address,
                'status' => match ($server['status'] ?? 'unknown') {
                    'active' => 'active',
                    'off' => 'inactive',
                    default => $record->status,
                },
                'bl_size_slug' => $server['size_slug'] ?? $record->bl_size_slug,
                'bl_region' => $server['region_slug'] ?? $record->bl_region,
                'bl_image' => $server['image_slug'] ?? $record->bl_image,
                'cpu_cores' => $server['vcpus'] ?? $record->cpu_cores,
                'memory_mb' => $server['memory_mb'] ?? $record->memory_mb,
                'disk_gb' => $server['disk_gb'] ?? $record->disk_gb,
                'bl_synced_at' => now(),
            ]);

            Notification::make()
                ->title('Sync Complete')
                ->body("VNode {$record->name} synced from BinaryLane.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Sync Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
