<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Cms\Filament\Resources\MenuResource\Pages;
use NetServa\Cms\Models\Menu;
use UnitEnum;

/**
 * Filament Resource for CMS Menus
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'Menus';

    protected static string|UnitEnum|null $navigationGroup = 'Cms';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Menu Items - Full width at top, no section wrapper
                Forms\Components\Repeater::make('items')
                    ->schema([
                        // Row 1: Label, URL, Icon, Order, Toggle
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Link text displayed to users')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('url')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Relative URL (e.g., /about) or full URL')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('icon')
                            ->maxLength(255)
                            ->helperText('Optional Heroicon name')
                            ->placeholder('heroicon-o-home')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('new_window')
                            ->hiddenLabel()
                            ->default(false)
                            ->columnSpan(1),

                        // Row 2: Children (full width)
                        Forms\Components\Repeater::make('children')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('url')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('icon')
                                    ->maxLength(255)
                                    ->placeholder('heroicon-o-document')
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('order')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(2),

                                Forms\Components\Toggle::make('new_window')
                                    ->hiddenLabel()
                                    ->default(false)
                                    ->columnSpan(1),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Submenu Item')
                            ->reorderableWithButtons()
                            ->addActionLabel('Add Submenu Item'),
                    ])
                    ->columns(12)
                    ->defaultItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Menu Item')
                    ->reorderableWithButtons()
                    ->cloneable()
                    ->addActionLabel('Add Menu Item')
                    ->columnSpanFull(),

                // Menu Details - At bottom with 3 columns
                Section::make('Menu Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal name for this menu'),

                        Forms\Components\TextInput::make('location')
                            ->required()
                            ->maxLength(255)
                            ->unique(Menu::class, 'location', ignoreRecord: true)
                            ->helperText('Unique identifier (e.g., header, footer, sidebar)'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active menus are displayed on the frontend'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('items')
                    ->label('Items Count')
                    ->getStateUsing(fn (Menu $record): int => count($record->items ?? []))
                    ->sortable(false),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('location')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All menus')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
