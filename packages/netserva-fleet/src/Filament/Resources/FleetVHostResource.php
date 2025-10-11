<?php

namespace NetServa\Fleet\Filament\Resources;

use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\FleetVHostResource\Pages;
use NetServa\Fleet\Models\FleetVHost;

/**
 * Fleet VHost Resource
 *
 * Manages VM/CT instances in the fleet hierarchy
 */
class FleetVHostResource extends Resource
{
    protected static ?string $model = FleetVHost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'VHosts';

    protected static ?string $modelLabel = 'VHost';

    protected static ?string $pluralModelLabel = 'VHosts';

    protected static string|\UnitEnum|null $navigationGroup = '🚀 Fleet Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('domain')
                            ->required()
                            ->helperText('Primary domain/identifier for this instance'),

                        Forms\Components\TextInput::make('slug')
                            ->helperText('Auto-generated from domain if left empty'),

                        Forms\Components\Select::make('vnode_id')
                            ->relationship('vnode', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Server this instance runs on'),

                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Instance Details')
                    ->schema([
                        Forms\Components\Select::make('instance_type')
                            ->options([
                                'vm' => 'Virtual Machine',
                                'ct' => 'Container (LXC)',
                                'lxc' => 'LXC Container',
                                'docker' => 'Docker Container',
                            ])
                            ->helperText('Type of virtualization'),

                        Forms\Components\TextInput::make('instance_id')
                            ->helperText('Provider-specific instance ID'),

                        Forms\Components\TextInput::make('cpu_cores')
                            ->numeric()
                            ->helperText('Number of CPU cores'),

                        Forms\Components\TextInput::make('memory_mb')
                            ->numeric()
                            ->helperText('Memory allocation in MB'),

                        Forms\Components\TextInput::make('disk_gb')
                            ->numeric()
                            ->helperText('Disk allocation in GB'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Network Configuration')
                    ->schema([
                        Forms\Components\TagsInput::make('ip_addresses')
                            ->helperText('IP addresses assigned to this instance'),

                        Forms\Components\TagsInput::make('services')
                            ->helperText('Services running on this instance'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('File System Integration')
                    ->schema([
                        Forms\Components\TextInput::make('var_file_path')
                            ->helperText('Path to NetServa var file')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('var_file_modified_at')
                            ->helperText('Last modification time of var file')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
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
                            ->helperText('Enable/disable this VHost'),

                        Forms\Components\DateTimePicker::make('last_discovered_at')
                            ->disabled()
                            ->helperText('Last discovery time'),

                        Forms\Components\Textarea::make('last_error')
                            ->disabled()
                            ->helperText('Last discovery error')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('instance_icon')
                    ->label('')
                    ->getStateUsing(fn (FleetVHost $record) => $record->getInstanceTypeIcon())
                    ->alignCenter()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('vnode.name')
                    ->label('VNode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('vnode.vsite.name')
                    ->label('VSite')
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('instance_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vm' => 'info',
                        'ct', 'lxc' => 'success',
                        'docker' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('primary_ip')
                    ->label('Primary IP')
                    ->getStateUsing(fn (FleetVHost $record) => $record->primary_ip)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('resource_summary')
                    ->label('Resources')
                    ->getStateUsing(function (FleetVHost $record): string {
                        $parts = [];
                        if ($record->cpu_cores) {
                            $parts[] = "{$record->cpu_cores}C";
                        }
                        if ($record->memory_gb) {
                            $parts[] = "{$record->memory_gb}GB";
                        }
                        if ($record->disk_gb) {
                            $parts[] = "{$record->disk_gb}GB";
                        }

                        return implode(' / ', $parts) ?: 'Unknown';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_types')
                    ->label('Services')
                    ->getStateUsing(function (FleetVHost $record): string {
                        $types = [];
                        if ($record->isWebServer()) {
                            $types[] = 'Web';
                        }
                        if ($record->isMailServer()) {
                            $types[] = 'Mail';
                        }
                        if ($record->isDatabaseServer()) {
                            $types[] = 'DB';
                        }

                        return implode(', ', $types) ?: 'Unknown';
                    })
                    ->badge()
                    ->separator(',')
                    ->toggleable(),

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
                Tables\Filters\SelectFilter::make('vnode')
                    ->relationship('vnode', 'name'),

                Tables\Filters\SelectFilter::make('vsite')
                    ->relationship('vnode.vsite', 'name')
                    ->label('VSite'),

                Tables\Filters\SelectFilter::make('instance_type')
                    ->options([
                        'vm' => 'Virtual Machine',
                        'ct' => 'Container',
                        'lxc' => 'LXC',
                        'docker' => 'Docker',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                        'error' => 'Error',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),

                Tables\Filters\Filter::make('has_var_file')
                    ->query(fn ($query) => $query->whereNotNull('var_file_path'))
                    ->label('Has Var File'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_validation')
                    ->label('Validation')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->url(fn (FleetVHost $record): string => static::getUrl('view-validation', ['record' => $record]))
                    ->visible(fn (FleetVHost $record) => in_array($record->migration_status, ['discovered', 'validated', 'failed'])),

                Tables\Actions\Action::make('view_migration_log')
                    ->label('Migration Log')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (FleetVHost $record): string => static::getUrl('view-migration-log', ['record' => $record]))
                    ->visible(fn (FleetVHost $record) => in_array($record->migration_status, ['migrated', 'failed'])),

                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Rollback Migration')
                    ->modalDescription(fn (FleetVHost $record): string => "This will restore {$record->domain} to its pre-migration state. The vhost will be marked as 'validated' after rollback.")
                    ->form(function (FleetVHost $record) {
                        $migrationService = app(\NetServa\Cli\Services\MigrationExecutionService::class);
                        $result = $migrationService->listRollbackPoints($record);

                        $options = [];
                        if ($result['success'] && ! empty($result['rollback_points'])) {
                            foreach ($result['rollback_points'] as $point) {
                                $options[$point['path']] = $point['filename'].' ('.$point['created_at'].')';
                            }
                        }

                        return [
                            Forms\Components\Select::make('archive')
                                ->label('Rollback Point')
                                ->options($options)
                                ->required()
                                ->helperText('Select which backup to restore from')
                                ->default(fn () => array_key_first($options)), // Default to most recent
                        ];
                    })
                    ->action(function (FleetVHost $record, array $data) {
                        $migrationService = app(\NetServa\Cli\Services\MigrationExecutionService::class);
                        $result = $migrationService->rollbackVhost($record, $data['archive']);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('Rollback Successful')
                                ->body("VHost {$record->domain} has been restored to validated status.")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Rollback Failed')
                                ->body($result['error'] ?? 'An error occurred during rollback')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (FleetVHost $record) => $record->migration_status === 'migrated' && $record->rollback_available),

                Tables\Actions\Action::make('sync_var_file')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (FleetVHost $record) {
                        $synced = $record->syncWithVarFile();

                        if ($synced) {
                            \Filament\Notifications\Notification::make()
                                ->title('Var File Synced')
                                ->body("VHost {$record->domain} synced with var file.")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('No Sync Needed')
                                ->body("VHost {$record->domain} is already up to date.")
                                ->info()
                                ->send();
                        }
                    })
                    ->visible(fn (FleetVHost $record) => ! empty($record->var_file_path)),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('migrate_selected')
                        ->label('Migrate Selected to NS 3.0')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Migrate Selected VHosts to NetServa 3.0')
                        ->modalDescription(fn ($records) => "This will migrate {$records->count()} vhost(s) to NS 3.0 structure. Backups will be created automatically for each vhost.")
                        ->modalSubmitActionLabel('Migrate All')
                        ->action(function ($records) {
                            $migrationService = app(\NetServa\Cli\Services\MigrationExecutionService::class);
                            $results = ['success' => 0, 'failed' => 0, 'errors' => []];

                            foreach ($records as $vhost) {
                                // Only migrate validated vhosts
                                if ($vhost->migration_status !== 'validated') {
                                    $results['failed']++;
                                    $results['errors'][] = "{$vhost->domain}: Not in validated status (current: {$vhost->migration_status})";

                                    continue;
                                }

                                $result = $migrationService->migrateVhost($vhost);

                                if ($result['success']) {
                                    $results['success']++;
                                } else {
                                    $results['failed']++;
                                    $results['errors'][] = "{$vhost->domain}: {$result['error']}";
                                }
                            }

                            // Build notification message
                            $message = "Migrated {$results['success']} vhost(s) successfully.";
                            if ($results['failed'] > 0) {
                                $message .= " {$results['failed']} vhost(s) failed.";
                            }

                            $notificationType = $results['failed'] === 0 ? 'success' : ($results['success'] > 0 ? 'warning' : 'danger');

                            $notification = \Filament\Notifications\Notification::make()
                                ->title('Bulk Migration Complete')
                                ->body($message);

                            // Add error details if any
                            if (! empty($results['errors'])) {
                                $errorList = implode("\n", array_slice($results['errors'], 0, 5)); // Show first 5 errors
                                if (count($results['errors']) > 5) {
                                    $errorList .= "\n... and ".(count($results['errors']) - 5).' more';
                                }
                                $notification->body($message."\n\nErrors:\n".$errorList);
                            }

                            $notification->{$notificationType}()->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn ($records) => $records->contains(fn ($record) => $record->migration_status === 'validated')),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('domain');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\Section::make('VHost Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('domain')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('vnode.name')
                            ->label('VNode'),

                        Infolists\Components\TextEntry::make('vnode.vsite.name')
                            ->label('VSite'),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Instance Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('instance_type')
                            ->badge(),

                        Infolists\Components\TextEntry::make('instance_id'),

                        Infolists\Components\TextEntry::make('cpu_cores')
                            ->suffix(' cores'),

                        Infolists\Components\TextEntry::make('memory_mb')
                            ->formatStateUsing(fn ($state) => $state ? round($state / 1024, 1).' GB' : null),

                        Infolists\Components\TextEntry::make('disk_gb')
                            ->suffix(' GB'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Network & Services')
                    ->schema([
                        Infolists\Components\TextEntry::make('ip_addresses')
                            ->listWithLineBreaks()
                            ->bulleted(),

                        Infolists\Components\TextEntry::make('services')
                            ->listWithLineBreaks()
                            ->bulleted(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('NetServa Integration')
                    ->schema([
                        Infolists\Components\TextEntry::make('var_file_path')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('var_file_modified_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('netserva_paths')
                            ->label('NetServa Paths')
                            ->getStateUsing(function (FleetVHost $record): array {
                                $paths = $record->getNetServasPaths();

                                return [
                                    "VHOST: {$paths['vhost']}",
                                    "UPATH: {$paths['upath']}",
                                    "WPATH: {$paths['wpath']}",
                                    "MPATH: {$paths['mpath']}",
                                ];
                            })
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Discovery Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_discovered_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('status')
                            ->badge(),

                        Infolists\Components\TextEntry::make('last_error')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVHosts::route('/'),
            'create' => Pages\CreateFleetVHost::route('/create'),
            'view' => Pages\ViewFleetVHost::route('/{record}'),
            // Temporarily disabled - needs Filament 4.1 compatibility fixes
            // 'view-validation' => Pages\ViewValidation::route('/{record}/validation'),
            // 'view-migration-log' => Pages\ViewMigrationLog::route('/{record}/migration-log'),
            'edit' => Pages\EditFleetVHost::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
