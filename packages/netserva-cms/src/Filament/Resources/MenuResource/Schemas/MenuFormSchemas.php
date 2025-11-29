<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\MenuResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
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
     * Menu Items - compact table repeater with nested children
     */
    public static function getItemsSchema(): array
    {
        return [
            Forms\Components\Repeater::make('items')
                ->hiddenLabel()
                ->schema([
                    Grid::make(4)->schema([
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Label')
                            ->hiddenLabel(),

                        Forms\Components\TextInput::make('url')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/path')
                            ->hiddenLabel(),

                        Forms\Components\TextInput::make('icon')
                            ->maxLength(255)
                            ->placeholder('heroicon-o-...')
                            ->hiddenLabel(),

                        Forms\Components\Toggle::make('new_window')
                            ->label('↗')
                            ->inline(false),
                    ]),

                    // Nested children repeater
                    Forms\Components\Repeater::make('children')
                        ->label('Submenu')
                        ->schema([
                            Grid::make(4)->schema([
                                Forms\Components\TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Sublabel')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('url')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('/subpath')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('icon')
                                    ->maxLength(255)
                                    ->placeholder('heroicon-o-...')
                                    ->hiddenLabel(),

                                Forms\Components\Toggle::make('new_window')
                                    ->label('↗')
                                    ->inline(false),
                            ]),
                        ])
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->addActionLabel('+ Submenu')
                        ->collapsed()
                        ->columnSpanFull(),
                ])
                ->itemLabel(fn (array $state): ?string => ($state['label'] ?? 'Item').' → '.($state['url'] ?? ''))
                ->defaultItems(1)
                ->reorderableWithButtons()
                ->cloneable()
                ->collapsible()
                ->addActionLabel('Add Menu Item')
                ->columnSpanFull(),
        ];
    }
}
