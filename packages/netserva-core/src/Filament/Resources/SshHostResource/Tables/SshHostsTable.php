<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Models\SshKey;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\SshHostSyncService;

class SshHostsTable
{
    /**
     * Get form schema for create/edit modals
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('host')
                ->label('Host Alias')
                ->required()
                ->unique(ignorable: fn ($record) => $record)
                ->maxLength(255)
                ->placeholder('e.g., mrn, nsorg, prod-web')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Short name used in SSH config and commands'),

            TextInput::make('hostname')
                ->label('Hostname / IP')
                ->required()
                ->maxLength(255)
                ->placeholder('192.168.1.100 or server.example.com')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('IP address or fully qualified domain name'),

            Grid::make(3)
                ->schema([
                    TextInput::make('port')
                        ->numeric()
                        ->default(22)
                        ->minValue(1)
                        ->maxValue(65535)
                        ->required(),

                    TextInput::make('user')
                        ->default('root')
                        ->maxLength(255)
                        ->required(),

                    Select::make('identity_file')
                        ->label('SSH Key')
                        ->options(fn () => SshKey::active()
                            ->pluck('name')
                            ->mapWithKeys(fn ($name) => ["~/.ssh/keys/{$name}" => $name])
                            ->toArray())
                        ->searchable()
                        ->placeholder('Select key...'),
                ]),

            TextInput::make('jump_host')
                ->label('Jump Host')
                ->placeholder('bastion')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('ProxyJump host alias for tunneling'),

            Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description'),

            Toggle::make('is_active')
                ->label('Disabled')
                ->default(false)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Disabled hosts are not synced to ~/.ssh/hosts/'),
        ];
    }

    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host')
                    ->label('Alias')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Host alias copied'),

                TextColumn::make('hostname')
                    ->label('Hostname / IP')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('port')
                    ->sortable(),

                TextColumn::make('user')
                    ->sortable(),

                TextColumn::make('identity_file')
                    ->label('SSH Key')
                    ->placeholder('None')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_reachable')
                    ->label('Reachable')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('last_tested_at')
                    ->label('Last Test')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_reachable')
                    ->label('Reachable'),
                SelectFilter::make('identity_file')
                    ->label('SSH Key')
                    ->options(fn () => \NetServa\Core\Models\SshKey::pluck('name', 'name')->toArray()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::Medium)
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->schema(fn () => self::getFormSchema()),
                    DeleteAction::make(),
                    Action::make('testConnection')
                        ->label('Test Connection')
                        ->icon(Heroicon::OutlinedWifi)
                        ->color('success')
                        ->action(function (SshHost $record) {
                            try {
                                $service = app(RemoteExecutionService::class);
                                $result = $service->exec($record->host, 'echo "Connection OK"');

                                if ($result['success']) {
                                    $record->update([
                                        'is_reachable' => true,
                                        'last_tested_at' => now(),
                                        'last_error' => null,
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Connection Successful')
                                        ->body("Connected to {$record->hostname}")
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Connection failed');
                                }
                            } catch (\Exception $e) {
                                $record->update([
                                    'is_reachable' => false,
                                    'last_tested_at' => now(),
                                    'last_error' => $e->getMessage(),
                                ]);

                                Notification::make()
                                    ->danger()
                                    ->title('Connection Failed')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                    Action::make('syncToFile')
                        ->label('Sync to File')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->color('warning')
                        ->action(function (SshHost $record) {
                            $service = app(SshHostSyncService::class);

                            if ($service->syncHost($record)) {
                                Notification::make()
                                    ->success()
                                    ->title('Config Synced')
                                    ->body("Synced to ~/.ssh/hosts/{$record->host}")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Sync Failed')
                                    ->send();
                            }
                        }),
                    Action::make('syncFromFile')
                        ->label('Sync from File')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('info')
                        ->action(function (SshHost $record) {
                            $service = app(SshHostSyncService::class);
                            $result = $service->importSingleHost($record->host);

                            if ($result) {
                                Notification::make()
                                    ->success()
                                    ->title('Config Imported')
                                    ->body("Imported from ~/.ssh/hosts/{$record->host}")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Import Failed')
                                    ->body("File ~/.ssh/hosts/{$record->host} not found or invalid")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                Action::make('importFromFilesystem')
                    ->label('Import from ~/.ssh/hosts/')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(function () {
                        $service = app(SshHostSyncService::class);
                        $results = $service->importFromFilesystem();

                        Notification::make()
                            ->success()
                            ->title('Import Complete')
                            ->body("Imported: {$results['imported']}, Skipped: {$results['skipped']}")
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Import SSH Hosts')
                    ->modalDescription('Import existing SSH host configurations from ~/.ssh/hosts/ into the database.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('testSelected')
                        ->label('Test Selected')
                        ->icon(Heroicon::OutlinedWifi)
                        ->action(function (Collection $records) {
                            $success = 0;
                            $failed = 0;
                            $service = app(RemoteExecutionService::class);

                            foreach ($records as $record) {
                                try {
                                    $result = $service->exec($record->host, 'echo "OK"');
                                    $record->update([
                                        'is_reachable' => $result['success'],
                                        'last_tested_at' => now(),
                                        'last_error' => $result['success'] ? null : ($result['error'] ?? 'Failed'),
                                    ]);
                                    $result['success'] ? $success++ : $failed++;
                                } catch (\Exception $e) {
                                    $record->update([
                                        'is_reachable' => false,
                                        'last_tested_at' => now(),
                                        'last_error' => $e->getMessage(),
                                    ]);
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Connection Tests Complete')
                                ->body("Success: {$success}, Failed: {$failed}")
                                ->send();
                        }),
                    BulkAction::make('syncSelected')
                        ->label('Sync Selected')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Collection $records) {
                            $service = app(SshHostSyncService::class);
                            $synced = 0;

                            foreach ($records as $record) {
                                if ($service->syncHost($record)) {
                                    $synced++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Sync Complete')
                                ->body("Synced {$synced} hosts to filesystem")
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
