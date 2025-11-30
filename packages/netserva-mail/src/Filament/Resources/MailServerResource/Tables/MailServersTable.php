<?php

namespace NetServa\Mail\Filament\Resources\MailServerResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailServerResource;

class MailServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('hostname')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('infrastructureNode.name')
                    ->label('Node')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('server_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'postfix_dovecot' => 'primary',
                        'exim_dovecot' => 'info',
                        'sendmail_courier' => 'warning',
                        'custom' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'postfix_dovecot' => 'Postfix + Dovecot',
                        'exim_dovecot' => 'Exim + Dovecot',
                        'sendmail_courier' => 'Sendmail + Courier',
                        'custom' => 'Custom',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('public_ip')
                    ->label('Public IP')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'healthy' => 'success',
                        'warning' => 'warning',
                        'error' => 'danger',
                        'maintenance' => 'info',
                        'unknown' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('server_type')
                    ->label('Server Type')
                    ->options([
                        'postfix_dovecot' => 'Postfix + Dovecot',
                        'exim_dovecot' => 'Exim + Dovecot',
                        'sendmail_courier' => 'Sendmail + Courier',
                        'custom' => 'Custom',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'healthy' => 'Healthy',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'maintenance' => 'Maintenance',
                        'unknown' => 'Unknown',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueLabel('Primary only')
                    ->falseLabel('Secondary only')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit mail server')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => MailServerResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete mail server'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
