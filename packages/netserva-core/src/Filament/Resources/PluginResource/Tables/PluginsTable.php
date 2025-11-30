<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\PluginResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Core\Foundation\PluginRegistry;

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

                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('gray'),

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
                    ->hiddenLabel()
                    ->tooltip(fn ($record) => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(fn ($record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_enabled' => ! $record->is_enabled])),

                Action::make('viewDetails')
                    ->hiddenLabel()
                    ->tooltip('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        Grid::make(3)->schema([
                            TextEntry::make('package_name')
                                ->label('Package'),
                            TextEntry::make('source'),
                            TextEntry::make('category'),
                        ]),
                        TextEntry::make('plugin_class')
                            ->label('Class'),
                        TextEntry::make('description')
                            ->placeholder('No description'),
                    ]),

                Action::make('editNavigation')
                    ->hiddenLabel()
                    ->tooltip('Edit Navigation')
                    ->icon('heroicon-o-bars-3')
                    ->color('gray')
                    ->modalHeading('Edit Navigation Settings')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->form([
                        Grid::make(3)->schema([
                            TextInput::make('navigation_sort')
                                ->label('Sort Order')
                                ->numeric()
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Lower numbers appear first'),

                            TextInput::make('navigation_group')
                                ->label('Group Label')
                                ->placeholder(fn ($record) => $record->getNavigationGroupName())
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Override default name'),

                            TextInput::make('navigation_icon')
                                ->label('Icon')
                                ->placeholder(fn ($record) => $record->getNavigationIcon())
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('e.g., heroicon-o-rocket-launch'),
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

                Action::make('syncFromComposer')
                    ->hiddenLabel()
                    ->tooltip('Sync from composer.json')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function ($record) {
                        $registry = app(PluginRegistry::class);

                        if ($registry->syncPlugin($record)) {
                            Notification::make()
                                ->success()
                                ->title('Plugin Synced')
                                ->body("Updated metadata from composer.json")
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('Sync Failed')
                                ->body("Could not read composer.json for this plugin")
                                ->send();
                        }
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
