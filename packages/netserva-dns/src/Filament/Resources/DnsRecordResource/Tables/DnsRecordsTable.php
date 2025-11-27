<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DnsRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => rtrim($state, '.')),
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Content')
                    ->searchable()
                    ->limit(45)
                    ->formatStateUsing(fn ($state, $record) => match ($record->type) {
                        'TXT' => trim($state, '"'),
                        'A', 'AAAA' => $state,
                        default => rtrim($state, '.'),
                    }),
                TextColumn::make('ttl')
                    ->label('TTL')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => match (true) {
                        $state === 1 => 'Auto',  // Cloudflare auto TTL
                        $state === null || $state === 0 => 'Auto',
                        default => (string) $state,
                    }),
                TextColumn::make('dnsZone.name')
                    ->label('Zone')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('md')
                    ->modalFooterActionsAlignment('end')
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Extract subdomain from FQDN for editing
                        if (isset($data['name']) && $record->zone) {
                            $zoneName = rtrim($record->zone->name, '.');
                            $name = rtrim($data['name'], '.');

                            // If name equals zone name, it's the apex
                            if ($name === $zoneName) {
                                $data['name'] = '@';
                            } elseif (str_ends_with($name, '.'.$zoneName)) {
                                // Extract subdomain part
                                $data['name'] = substr($name, 0, -strlen('.'.$zoneName));
                            } else {
                                $data['name'] = $name;
                            }
                        }

                        // For TXT records, strip surrounding quotes for editing
                        if (isset($data['type'], $data['content']) && $data['type'] === 'TXT') {
                            $data['content'] = trim($data['content'], '"');
                        }

                        // For MX/SRV records, extract priority from content
                        if (isset($data['type'], $data['content']) && in_array($data['type'], ['MX', 'SRV'])) {
                            $parts = explode(' ', $data['content'], 2);
                            if (count($parts) === 2 && is_numeric($parts[0])) {
                                $data['priority'] = (int) $parts[0];
                                $data['content'] = rtrim($parts[1], '.');
                            }
                        }

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Convert subdomain back to FQDN
                        if (isset($data['name']) && $record->zone) {
                            $zoneName = rtrim($record->zone->name, '.');
                            $name = trim($data['name']);

                            if ($name === '@' || $name === '' || $name === $zoneName || $name === $zoneName.'.') {
                                // Zone apex
                                $data['name'] = $zoneName.'.';
                            } elseif (str_ends_with($name, '.'.$zoneName.'.') || str_ends_with($name, '.'.$zoneName)) {
                                // Already FQDN, just ensure trailing dot
                                $data['name'] = rtrim($name, '.').'.';
                            } else {
                                // Subdomain - prepend to zone name
                                $data['name'] = rtrim($name, '.').'.'.$zoneName.'.';
                            }
                        }

                        // For TXT records, wrap content in quotes
                        if (isset($data['type'], $data['content']) && $data['type'] === 'TXT') {
                            $content = trim($data['content'], '"');
                            $data['content'] = '"'.$content.'"';
                        }

                        // Ensure content ends with dot for hostnames (MX, CNAME, NS, etc.)
                        if (isset($data['type'], $data['content']) && in_array($data['type'], ['MX', 'SRV', 'CNAME', 'NS', 'PTR'])) {
                            if (! str_ends_with($data['content'], '.')) {
                                $data['content'] = $data['content'].'.';
                            }
                        }

                        return $data;
                    })
                    ->after(function ($record) {
                        // Sync to remote DNS provider
                        // Explicitly load relationships to ensure they're available
                        $record->load('zone.dnsProvider');
                        $zone = $record->zone;
                        $provider = $zone?->dnsProvider;

                        if (! $provider) {
                            \Log::warning('DNS Record sync skipped: no provider found', [
                                'record_id' => $record->id,
                                'zone_id' => $zone?->id,
                            ]);

                            return;
                        }

                        \Log::info('Syncing DNS record to provider', [
                            'record' => $record->name,
                            'provider' => $provider->name,
                            'type' => $provider->type,
                        ]);

                        try {
                            if ($provider->type === 'cloudflare') {
                                // Sync to Cloudflare
                                $service = app(\NetServa\Dns\Services\DnsProviderService::class);
                                $success = $service->updateRecord($record, [
                                    'type' => $record->type,
                                    'name' => rtrim($record->name, '.'),
                                    'content' => trim($record->content, '"'),
                                    'ttl' => $record->ttl ?? 1,
                                    'priority' => $record->priority,
                                ]);

                                if ($success) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Synced to Cloudflare')
                                        ->body("Record updated on {$provider->name}")
                                        ->success()
                                        ->send();

                                    $record->update(['last_synced' => now()]);
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Cloudflare Sync Failed')
                                        ->body('Failed to update record on Cloudflare')
                                        ->danger()
                                        ->send();
                                }
                            } elseif ($provider->type === 'powerdns') {
                                // Sync to PowerDNS
                                $config = $provider->connection_config ?? [];
                                if (! ($config['ssh_host'] ?? null)) {
                                    return;
                                }

                                $tunnelService = app(\NetServa\Dns\Services\PowerDnsTunnelService::class);

                                // Check if name or type changed - need to delete old record first
                                $originalName = $record->getOriginal('name');
                                $originalType = $record->getOriginal('type');
                                $nameChanged = $originalName && $originalName !== $record->name;
                                $typeChanged = $originalType && $originalType !== $record->type;

                                if ($nameChanged || $typeChanged) {
                                    // Delete the old record first
                                    $deleteRrsets = [[
                                        'name' => $originalName,
                                        'type' => $originalType,
                                        'changetype' => 'DELETE',
                                    ]];
                                    $tunnelService->updateRecords($provider, $zone->name, $deleteRrsets);
                                }

                                // Build rrset for PowerDNS PATCH
                                $content = $record->content;
                                if (in_array($record->type, ['MX', 'SRV']) && $record->priority) {
                                    $content = $record->priority.' '.$content;
                                }

                                $rrsets = [[
                                    'name' => $record->name,
                                    'type' => $record->type,
                                    'ttl' => $record->ttl ?? 300,
                                    'changetype' => 'REPLACE',
                                    'records' => [[
                                        'content' => $content,
                                        'disabled' => $record->disabled ?? false,
                                    ]],
                                ]];

                                $result = $tunnelService->updateRecords($provider, $zone->name, $rrsets);

                                if ($result['success'] ?? false) {
                                    // Increment SOA serial after record update
                                    $tunnelService->increaseSerial($provider, $zone->name);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Synced to PowerDNS')
                                        ->body("Record updated on {$provider->name}")
                                        ->success()
                                        ->send();

                                    $record->update(['last_synced' => now()]);
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Sync Failed')
                                        ->body($result['message'] ?? 'Unknown error')
                                        ->danger()
                                        ->send();
                                }
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Sync Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                DeleteAction::make()
                    ->before(function ($record) {
                        // Sync delete to remote DNS provider BEFORE local delete
                        $zone = $record->zone;
                        $provider = $zone?->dnsProvider;

                        if (! $provider) {
                            return;
                        }

                        try {
                            if ($provider->type === 'cloudflare') {
                                // Delete from Cloudflare
                                $service = app(\NetServa\Dns\Services\DnsProviderService::class);
                                $success = $service->deleteRecord($record);

                                if ($success) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Deleted from Cloudflare')
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Cloudflare Delete Failed')
                                        ->danger()
                                        ->send();
                                }
                            } elseif ($provider->type === 'powerdns') {
                                // Delete from PowerDNS
                                $config = $provider->connection_config ?? [];
                                if (! ($config['ssh_host'] ?? null)) {
                                    return;
                                }

                                $tunnelService = app(\NetServa\Dns\Services\PowerDnsTunnelService::class);

                                $rrsets = [[
                                    'name' => $record->name,
                                    'type' => $record->type,
                                    'changetype' => 'DELETE',
                                ]];

                                $result = $tunnelService->updateRecords($provider, $zone->name, $rrsets);

                                // Increment SOA serial after record delete
                                if ($result['success'] ?? false) {
                                    $tunnelService->increaseSerial($provider, $zone->name);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Deleted from PowerDNS')
                                        ->success()
                                        ->send();
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::error('DNS delete sync failed: '.$e->getMessage());
                            \Filament\Notifications\Notification::make()
                                ->title('Delete Sync Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
