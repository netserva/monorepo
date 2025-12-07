<?php

declare(strict_types=1);

namespace NetServa\Crm\Filament\Resources\CrmClientResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * VSites Relation Manager
 *
 * This relation manager is only registered when Fleet integration is available.
 * It allows assigning/unassigning VSites to clients.
 */
class VsitesRelationManager extends RelationManager
{
    protected static string $relationship = 'vsites';

    protected static ?string $title = 'VSites';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-server-stack';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'local' => 'info',
                        'binarylane' => 'success',
                        'customer' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('location')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('vnodes_count')
                    ->label('VNodes')
                    ->counts('vnodes')
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'description']),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
