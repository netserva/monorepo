<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\MenuResource\Schemas;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use NetServa\Cms\Models\Menu;

/**
 * Shared form schemas for Menu create/edit pages
 *
 * Used by modal actions in page headers for a clean, mobile-friendly UX
 */
class MenuFormSchemas
{
    /**
     * Menu Details: Name, Location, Active
     */
    public static function getDetailsSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('Main Navigation')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Internal name for this menu'),

            Forms\Components\TextInput::make('location')
                ->required()
                ->maxLength(255)
                ->unique(Menu::class, 'location', ignoreRecord: true)
                ->placeholder('header')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Unique identifier (e.g., header, footer, sidebar)'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Only active menus are displayed on the frontend'),
        ];
    }

    /**
     * Menu Items - table repeater with modal-based submenu editing
     */
    public static function getItemsSchema(): array
    {
        return [
            Forms\Components\Repeater::make('items')
                ->hiddenLabel()
                ->table([
                    TableColumn::make('Label')
                        ->markAsRequired(),
                    TableColumn::make('URL')
                        ->markAsRequired(),
                    TableColumn::make('↗')
                        ->alignment(Alignment::Center)
                        ->width('50px'),
                ])
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Home'),

                    Forms\Components\TextInput::make('url')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/'),

                    Forms\Components\Toggle::make('new_window'),

                    // Hidden field to store children data
                    Forms\Components\Hidden::make('children')
                        ->default([]),
                ])
                ->extraItemActions([
                    Action::make('editSubmenu')
                        ->icon(Heroicon::QueueList)
                        ->color(fn (array $arguments, Repeater $component): string => count($component->getRawItemState($arguments['item'])['children'] ?? []) > 0 ? 'primary' : 'gray')
                        ->badge(fn (array $arguments, Repeater $component): ?string => ($count = count($component->getRawItemState($arguments['item'])['children'] ?? [])) > 0 ? (string) $count : null)
                        ->tooltip('Edit submenu items')
                        ->modalHeading(fn (array $arguments, Repeater $component): string => 'Submenu: '.($component->getRawItemState($arguments['item'])['label'] ?? 'Item'))
                        ->modalWidth('lg')
                        ->fillForm(fn (array $arguments, Repeater $component): array => [
                            'children' => $component->getRawItemState($arguments['item'])['children'] ?? [],
                        ])
                        ->form([
                            Forms\Components\Repeater::make('children')
                                ->hiddenLabel()
                                ->schema([
                                    Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->required()
                                            ->placeholder('Label'),
                                        Forms\Components\TextInput::make('url')
                                            ->required()
                                            ->placeholder('/path'),
                                        Forms\Components\Toggle::make('new_window')
                                            ->label('↗')
                                            ->inline(false),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->reorderableWithButtons()
                                ->addActionLabel('Add Submenu Item'),
                        ])
                        ->action(function (array $arguments, array $data, Repeater $component): void {
                            $state = $component->getState();
                            $state[$arguments['item']]['children'] = $data['children'] ?? [];
                            $component->state($state);
                        }),
                ])
                ->compact()
                ->defaultItems(1)
                ->reorderableWithButtons()
                ->cloneable()
                ->addActionLabel('Add Item')
                ->columnSpanFull(),
        ];
    }
}
