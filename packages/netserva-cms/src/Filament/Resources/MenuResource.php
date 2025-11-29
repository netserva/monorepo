<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Cms\Filament\Resources\MenuResource\Pages;
use NetServa\Cms\Filament\Resources\MenuResource\Schemas\MenuFormSchemas;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Menus';

    protected static string|UnitEnum|null $navigationGroup = 'Cms';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        // Items-only form - settings accessed via modal button in page header
        return $schema->components(MenuFormSchemas::getItemsSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('items')
                    ->label('Items')
                    ->getStateUsing(fn (Menu $record): int => count($record->items ?? []))
                    ->sortable(false)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

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
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit menu'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete menu'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
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
