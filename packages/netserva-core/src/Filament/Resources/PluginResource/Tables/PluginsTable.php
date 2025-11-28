<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\PluginResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class PluginsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->reorderable('navigation_sort')
            ->defaultSort('navigation_sort')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('navigation_group')
                    ->label('Nav Group')
                    ->getStateUsing(fn ($record) => $record->getNavigationGroupName()),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label('')
                    ->tooltip(fn ($record) => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(fn ($record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_enabled' => ! $record->is_enabled])),

                Action::make('editNavigation')
                    ->label('')
                    ->tooltip('Edit Navigation')
                    ->icon('heroicon-o-bars-3')
                    ->color('gray')
                    ->modalHeading('Edit Navigation Settings')
                    ->form([
                        Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('navigation_sort')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->helperText('Lower numbers appear first'),

                                Forms\Components\TextInput::make('navigation_group')
                                    ->label('Group Label')
                                    ->placeholder(fn ($record) => $record->getNavigationGroupName())
                                    ->helperText('Override default name'),

                                Forms\Components\TextInput::make('navigation_icon')
                                    ->label('Icon')
                                    ->placeholder(fn ($record) => $record->getNavigationIcon())
                                    ->helperText('e.g., heroicon-o-rocket-launch'),
                            ]),
                    ])
                    ->fillForm(fn ($record) => [
                        'navigation_sort' => $record->navigation_sort,
                        'navigation_group' => $record->navigation_group,
                        'navigation_icon' => $record->navigation_icon,
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'navigation_sort' => $data['navigation_sort'] ?? 99,
                            'navigation_group' => $data['navigation_group'] ?: null,
                            'navigation_icon' => $data['navigation_icon'] ?: null,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_enabled' => true])),

                    BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_enabled' => false])),
                ]),
            ])
            ->paginated(false);
    }
}
