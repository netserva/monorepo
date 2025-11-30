<?php

namespace NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource;

class SslCertificateDeploymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('certificate.common_name')
                    ->label('Certificate')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->certificate?->subject_alternative_names
                        ? 'SANs: '.implode(', ', (array) $record->certificate->subject_alternative_names)
                        : null
                    ),

                Tables\Columns\TextColumn::make('server_identifier')
                    ->label('Server')
                    ->searchable(['server_hostname', 'infrastructureNode.name'])
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('server_hostname', $direction);
                    })
                    ->description(fn ($record) => $record->service_type ? strtoupper($record->service_type) : null),

                Tables\Columns\TextColumn::make('deployment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'success',
                        'renewal' => 'info',
                        'update' => 'warning',
                        'rollback' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record): string => $record->status_color)
                    ->sortable(),

                Tables\Columns\TextColumn::make('deployment_started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deployment_completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('duration_human')
                    ->label('Duration')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'deploying' => 'Deploying',
                        'deployed' => 'Deployed',
                        'failed' => 'Failed',
                        'rolled_back' => 'Rolled Back',
                    ]),

                Tables\Filters\SelectFilter::make('deployment_type')
                    ->label('Type')
                    ->options([
                        'new' => 'New Certificate',
                        'renewal' => 'Certificate Renewal',
                        'update' => 'Certificate Update',
                        'rollback' => 'Rollback',
                    ]),

                Tables\Filters\SelectFilter::make('service_type')
                    ->label('Service')
                    ->options([
                        'nginx' => 'Nginx',
                        'apache' => 'Apache',
                        'haproxy' => 'HAProxy',
                        'postfix' => 'Postfix',
                        'dovecot' => 'Dovecot',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit deployment')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => SslCertificateDeploymentResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete deployment'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([5, 10, 25, 50, 100]);
    }
}
