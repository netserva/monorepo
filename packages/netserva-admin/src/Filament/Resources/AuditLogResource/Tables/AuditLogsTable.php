<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\AuditLogResource\Tables;

use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable()
                    ->description(fn ($record) => $record->age)
                    ->since(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->default('System')
                    ->description(fn ($record) => $record->ip_address),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted', 'force_deleted' => 'danger',
                        'login', 'logout' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Resource')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A')
                    ->description(fn ($record) => $record->resource_name)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event_category')
                    ->label('Category')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'model' => 'gray',
                        'system' => 'info',
                        'security' => 'danger',
                        'user' => 'warning',
                        default => 'primary',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('severity_level')
                    ->label('Severity')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn ($record) => $record->severity_color)
                    ->icon(fn ($record) => match ($record->severity_level) {
                        'critical', 'high' => 'heroicon-o-exclamation-triangle',
                        'medium' => 'heroicon-o-exclamation-circle',
                        'low' => 'heroicon-o-information-circle',
                        default => null,
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'force_deleted' => 'Force Deleted',
                        'restored' => 'Restored',
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'password_changed' => 'Password Changed',
                        'permissions_changed' => 'Permissions Changed',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('event_category')
                    ->label('Category')
                    ->options([
                        'model' => 'Model',
                        'system' => 'System',
                        'security' => 'Security',
                        'user' => 'User',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('severity_level')
                    ->label('Severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('resource_type')
                    ->label('Resource Type')
                    ->options(function () {
                        return \NetServa\Core\Models\AuditLog::query()
                            ->whereNotNull('resource_type')
                            ->distinct()
                            ->pluck('resource_type')
                            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                            ->toArray();
                    }),

                Tables\Filters\Filter::make('occurred_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('From '.date('M d, Y', strtotime($data['from'])))
                                ->removeField('from');
                        }

                        if ($data['to'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Until '.date('M d, Y', strtotime($data['to'])))
                                ->removeField('to');
                        }

                        return $indicators;
                    }),

                Tables\Filters\TernaryFilter::make('security_sensitive')
                    ->label('Security Sensitive Only')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function ($query) {
                            $securityEvents = [
                                'login', 'logout', 'password_changed', 'permissions_changed',
                                'deleted', 'force_deleted', 'ssh_key_created', 'ssh_key_deleted',
                            ];
                            $query->whereIn('event_type', $securityEvents)
                                ->orWhereIn('severity_level', ['high', 'critical']);
                        }),
                        false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => "Audit Log Details: {$record->event_type_display}")
                    ->modalContent(fn ($record) => view('netserva-admin::audit-log-details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
}
