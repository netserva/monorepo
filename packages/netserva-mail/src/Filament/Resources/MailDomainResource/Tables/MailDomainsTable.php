<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources\MailDomainResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailDomainResource;

class MailDomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('mailServer.name')
                    ->label('Mail Server')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('enable_dkim')
                    ->label('DKIM')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('enable_spf')
                    ->label('SPF')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('enable_dmarc')
                    ->label('DMARC')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('relay_enabled')
                    ->label('Relay')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mailboxes_count')
                    ->label('Mailboxes')
                    ->counts('mailboxes')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All domains')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('relay_enabled')
                    ->label('Relay Enabled')
                    ->placeholder('All domains')
                    ->trueLabel('With relay')
                    ->falseLabel('Without relay'),

                SelectFilter::make('mail_server_id')
                    ->label('Mail Server')
                    ->relationship('mailServer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->searchable(false)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit mail domain')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => MailDomainResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete mail domain'),
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
