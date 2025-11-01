<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
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

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Menu Details')
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
                    ->columns(2),

                Forms\Components\Section::make('Menu Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Link text displayed to users'),

                                Forms\Components\TextInput::make('url')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Relative URL (e.g., /about) or full URL'),

                                Forms\Components\TextInput::make('icon')
                                    ->maxLength(255)
                                    ->helperText('Optional Heroicon name (e.g., heroicon-o-home)')
                                    ->placeholder('heroicon-o-home'),

                                Forms\Components\Toggle::make('new_window')
                                    ->label('Open in new window')
                                    ->default(false),

                                Forms\Components\TextInput::make('order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Sort order (lower numbers appear first)'),

                                Forms\Components\Repeater::make('children')
                                    ->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('url')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('icon')
                                            ->maxLength(255)
                                            ->placeholder('heroicon-o-document'),

                                        Forms\Components\Toggle::make('new_window')
                                            ->label('Open in new window')
                                            ->default(false),

                                        Forms\Components\TextInput::make('order')
                                            ->numeric()
                                            ->default(0),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Submenu Item')
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Add Submenu Item'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Menu Item')
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->addActionLabel('Add Menu Item'),
                    ]),
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
