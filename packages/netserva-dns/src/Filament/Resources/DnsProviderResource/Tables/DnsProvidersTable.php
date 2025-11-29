<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use NetServa\Dns\Filament\Resources\DnsProviderResource\Schemas\DnsProviderForm;

class DnsProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->description)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Provider Type')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'powerdns' => 'PowerDNS',
                        'cloudflare' => 'Cloudflare',
                        'route53' => 'AWS Route53',
                        'digitalocean' => 'DigitalOcean',
                        'linode' => 'Linode',
                        'hetzner' => 'Hetzner',
                        'custom' => 'Custom',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'powerdns' => 'primary',
                        'cloudflare' => 'warning',
                        'route53' => 'success',
                        'digitalocean', 'linode', 'hetzner' => 'info',
                        'custom' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'powerdns' => 'heroicon-o-server',
                        'cloudflare' => 'heroicon-o-cloud',
                        'route53' => 'heroicon-o-cloud',
                        'digitalocean' => 'heroicon-o-cloud',
                        'linode' => 'heroicon-o-cloud',
                        'hetzner' => 'heroicon-o-cloud',
                        'custom' => 'heroicon-o-cog',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('connection_summary')
                    ->label('Connection')
                    ->getStateUsing(function ($record) {
                        $config = $record->connection_config ?? [];
                        $sshHost = $config['ssh_host'] ?? null;
                        $apiEndpoint = $config['api_endpoint'] ?? 'localhost';
                        $apiPort = $config['api_port'] ?? 8081;
                        $email = $config['email'] ?? null;
                        $region = $config['region'] ?? null;

                        return match ($record->type) {
                            'powerdns' => $sshHost
                                ? "SSH: {$sshHost} → {$apiEndpoint}:{$apiPort}"
                                : $apiEndpoint,
                            'cloudflare' => $email
                                ? "Email: {$email}"
                                : 'API configured',
                            'route53' => $region
                                ? "Region: {$region}"
                                : 'AWS configured',
                            default => $apiEndpoint,
                        };
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $config = $record->connection_config ?? [];

                        return collect([
                            'Endpoint' => $config['api_endpoint'] ?? null,
                            'SSH Host' => $config['ssh_host'] ?? null,
                            'Port' => $config['api_port'] ?? null,
                            'Email' => $config['email'] ?? null,
                            'Region' => $config['region'] ?? null,
                            'Timeout' => isset($config['timeout']) ? "{$config['timeout']}s" : null,
                        ])->filter()->map(fn ($value, $key) => "{$key}: {$value}")->join("\n");
                    }),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->sortable()
                    ->toggleable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('zones_count')
                    ->label('Zones')
                    ->counts('zones')
                    ->sortable()
                    ->toggleable()
                    ->default(0),

                Tables\Columns\TextColumn::make('usage_summary')
                    ->label('Used By')
                    ->getStateUsing(function ($record) {
                        $counts = [
                            'Venues' => $record->venues()->count(),
                            'VSites' => $record->vsites()->count(),
                            'VNodes' => $record->vnodes()->count(),
                            'VHosts' => $record->vhosts()->count(),
                        ];

                        return collect($counts)
                            ->filter(fn ($count) => $count > 0)
                            ->map(fn ($count, $type) => "{$count} {$type}")
                            ->join(', ') ?: 'Unused';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable()
                    ->default(0),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Provider Type')
                    ->options([
                        'powerdns' => 'PowerDNS',
                        'cloudflare' => 'Cloudflare',
                        'route53' => 'AWS Route53',
                        'digitalocean' => 'DigitalOcean DNS',
                        'linode' => 'Linode DNS',
                        'hetzner' => 'Hetzner DNS',
                        'custom' => 'Custom Provider',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Active Status')
                    ->placeholder('All providers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('active', true),
                        false: fn (Builder $query) => $query->where('active', false),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\Filter::make('has_zones')
                    ->label('Has DNS Zones')
                    ->query(fn (Builder $query): Builder => $query->has('zones'))
                    ->toggle(),

                Tables\Filters\Filter::make('in_use')
                    ->label('Currently In Use')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->has('venues')
                            ->orHas('vsites')
                            ->orHas('vnodes')
                            ->orHas('vhosts');
                    }))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('has_ssh')
                    ->label('SSH Tunnel')
                    ->options([
                        'yes' => 'With SSH Tunnel',
                        'no' => 'Direct Connection',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['value'])) {
                            return $query;
                        }

                        return $data['value'] === 'yes'
                            ? $query->whereNotNull('connection_config->ssh_host')
                            : $query->whereNull('connection_config->ssh_host');
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::ExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => DnsProviderForm::getFormSchema()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->schema(fn () => DnsProviderForm::getFormSchema()),

                    Action::make('test_connection')
                        ->label('Test Connection')
                        ->icon('heroicon-o-signal')
                        ->color('info')
                        ->action(function ($record) {
                            // Use tunnel service for PowerDNS with SSH
                            if ($record->type === 'powerdns' && ($record->connection_config['ssh_host'] ?? null)) {
                                $tunnelService = app(\NetServa\Dns\Services\PowerDnsTunnelService::class);
                                $result = $tunnelService->testConnection($record);
                                $success = $result['success'] ?? false;
                                $message = $result['message'] ?? 'Unknown result';
                            } else {
                                $service = app(\NetServa\Dns\Services\DnsProviderService::class);
                                $success = $service->testConnection($record);
                                $message = $success ? 'Connection successful' : 'Connection failed';
                            }

                            if ($success) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Connection Successful')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Connection Failed')
                                    ->body($message)
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('sync_zones')
                        ->label('Sync Zones')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->visible(fn ($record) => $record->active)
                        ->requiresConfirmation()
                        ->modalHeading('Sync Zones')
                        ->modalDescription('This will fetch all zones and records from the remote DNS provider and update the local cache.')
                        ->action(function ($record) {
                            $results = ['zones' => 0, 'records' => 0, 'errors' => []];

                            try {
                                // Use tunnel service for PowerDNS with SSH
                                if ($record->type === 'powerdns' && ($record->connection_config['ssh_host'] ?? null)) {
                                    $tunnelService = app(\NetServa\Dns\Services\PowerDnsTunnelService::class);
                                    $remoteZones = $tunnelService->getZones($record);
                                } else {
                                    $client = $record->getClient();
                                    $remoteZones = $client->getAllZones();
                                }

                                foreach ($remoteZones as $remoteZone) {
                                    try {
                                        // Map PowerDNS kind values to our enum
                                        $kind = match ($remoteZone['kind'] ?? 'Primary') {
                                            'Master' => 'Primary',
                                            'Slave' => 'Secondary',
                                            'Native' => 'Native',
                                            'Forwarded' => 'Forwarded',
                                            default => 'Primary',
                                        };

                                        \NetServa\Dns\Models\DnsZone::updateOrCreate(
                                            [
                                                'dns_provider_id' => $record->id,
                                                'name' => $remoteZone['name'] ?? $remoteZone['id'],
                                            ],
                                            [
                                                'external_id' => $remoteZone['id'] ?? $remoteZone['name'],
                                                'kind' => $kind,
                                                'serial' => $remoteZone['serial'] ?? null,
                                                'active' => true,
                                                'last_synced' => now(),
                                                'provider_data' => $remoteZone,
                                            ]
                                        );
                                        $results['zones']++;
                                    } catch (\Exception $e) {
                                        $results['errors'][] = "Zone {$remoteZone['name']}: ".$e->getMessage();
                                    }
                                }

                                $record->update(['last_sync' => now()]);
                            } catch (\Exception $e) {
                                $results['errors'][] = 'Provider sync failed: '.$e->getMessage();
                            }

                            if (empty($results['errors'])) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Sync Complete')
                                    ->body("Synced {$results['zones']} zones from {$record->name}")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Sync Completed with Errors')
                                    ->body("Synced {$results['zones']} zones. Errors: ".implode(', ', $results['errors']))
                                    ->warning()
                                    ->send();
                            }
                        }),

                    // TODO: Implement usage page before enabling this action
                    // Action::make('view_usage')
                    //     ->label('View Usage')
                    //     ->icon('heroicon-o-chart-bar')
                    //     ->url(fn ($record) => route('filament.admin.resources.dns-providers.usage', $record))
                    //     ->openUrlInNewTab(),

                    DeleteAction::make(),
                ])->button()->label('Actions'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
