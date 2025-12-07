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
 * Domains Relation Manager
 *
 * This relation manager is only registered when Domain integration is available.
 * It allows assigning/unassigning domains (SwDomain) to clients.
 */
class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = 'Domains';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-globe-alt';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'pending', 'pending_registration' => 'warning',
                        'expired', 'cancelled' => 'danger',
                        'grace', 'redemption' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('registrant')
                    ->label('Registrant')
                    ->toggleable(),

                TextColumn::make('domain_expiry')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->domain_expiry?->isPast() ? 'danger' :
                        ($record->domain_expiry?->isBefore(now()->addDays(30)) ? 'warning' : null)),

                TextColumn::make('auto_renew')
                    ->label('Auto Renew')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['domain_name', 'registrant']),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ])
            ->defaultSort('domain_name');
    }
}
