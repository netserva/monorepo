<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DnsProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description)
                    ->weight('medium'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Provider Type')
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
                    ->colors([
                        'primary' => 'powerdns',
                        'warning' => 'cloudflare',
                        'success' => 'route53',
                        'info' => fn ($state): bool => in_array($state, ['digitalocean', 'linode', 'hetzner']),
                        'gray' => 'custom',
                    ])
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

                        return match ($record->type) {
                            'powerdns' => ($config['ssh_host'] ?? null)
                                ? "SSH: {$config['ssh_host']} → {$config['api_endpoint'] ?? 'localhost'}:{$config['api_port'] ?? 8081}"
                                : ($config['api_endpoint'] ?? 'Not configured'),
                            'cloudflare' => ($config['email'] ?? null)
                                ? "Email: {$config['email']}"
                                : 'API configured',
                            'route53' => ($config['region'] ?? null)
                                ? "Region: {$config['region']}"
                                : 'AWS configured',
                            default => ($config['api_endpoint'] ?? 'Not configured'),
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
            ->recordActions([
                Tables\Actions\ActionGroup::make([
                    EditAction::make(),

                    Tables\Actions\Action::make('test_connection')
                        ->label('Test Connection')
                        ->icon('heroicon-o-signal')
                        ->color('info')
                        ->action(function ($record) {
                            // TODO: Implement connection test
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Test')
                                ->body("Testing connection to {$record->name}...")
                                ->info()
                                ->send();
                        }),

                    Tables\Actions\Action::make('sync_zones')
                        ->label('Sync Zones')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->visible(fn ($record) => $record->active)
                        ->action(function ($record) {
                            // TODO: Implement zone sync
                            \Filament\Notifications\Notification::make()
                                ->title('Zone Sync')
                                ->body("Syncing zones from {$record->name}...")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('view_usage')
                        ->label('View Usage')
                        ->icon('heroicon-o-chart-bar')
                        ->url(fn ($record) => route('filament.admin.resources.dns-providers.usage', $record))
                        ->openUrlInNewTab(),

                    DeleteAction::make(),
                ])->button()->label('Actions'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->poll('30s');
    }
}
