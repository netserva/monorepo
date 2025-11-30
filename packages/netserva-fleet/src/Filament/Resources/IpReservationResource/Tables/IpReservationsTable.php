<?php

namespace NetServa\Fleet\Filament\Resources\IpReservationResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Fleet\Filament\Resources\IpReservationResource;
use NetServa\Fleet\Models\IpReservation;

class IpReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('ipNetwork.name')
                    ->label('Network')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('start_ip')
                    ->label('Start IP')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_ip')
                    ->label('End IP')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('address_count')
                    ->label('Addresses')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('reservation_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'static_range' => 'info',
                        'future_allocation' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => IpReservation::RESERVATION_TYPES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reservation_type')
                    ->label('Type')
                    ->options(IpReservation::RESERVATION_TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All reservations')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->searchable(false)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit reservation')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => IpReservationResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete reservation'),
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
