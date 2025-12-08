<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Fleet Hierarchy Widget
 *
 * Displays the infrastructure hierarchy: VSites → VNodes → VHosts
 */
class FleetHierarchy extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        if (! class_exists(FleetVsite::class)) {
            return $table
                ->heading('Fleet Infrastructure')
                ->description('Fleet management not available')
                ->paginated(false);
        }

        return $table
            ->heading('Fleet Infrastructure Hierarchy')
            ->description('VSites, VNodes, and hosted VHosts')
            ->query(
                FleetVsite::query()
                    ->withCount(['vnodes', 'vhosts'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('VSite')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office')
                    ->description(fn (FleetVsite $record) => $record->description),

                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->colors([
                        'primary' => 'local',
                        'success' => 'binarylane',
                        'warning' => 'customer',
                        'gray' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('technology')
                    ->badge()
                    ->colors([
                        'success' => 'proxmox',
                        'info' => 'incus',
                        'warning' => 'vps',
                        'gray' => 'hardware',
                    ]),

                Tables\Columns\TextColumn::make('location')
                    ->icon('heroicon-o-map-pin')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('vnodes_count')
                    ->label('VNodes')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state ?: '0'),

                Tables\Columns\TextColumn::make('vhosts_count')
                    ->label('VHosts')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => $state ?: '0'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'local' => 'Local',
                        'binarylane' => 'BinaryLane',
                        'customer' => 'Customer',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('technology')
                    ->options([
                        'proxmox' => 'Proxmox',
                        'incus' => 'Incus',
                        'vps' => 'VPS',
                        'hardware' => 'Hardware',
                        'router' => 'Router',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All VSites')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->toolbarActions([
                // No toolbar actions for dashboard widget
            ])
            ->defaultSort('name')
            ->poll('30s');
    }
}
