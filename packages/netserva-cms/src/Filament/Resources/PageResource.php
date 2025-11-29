<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Cms\Filament\Resources\PageResource\Pages;
use NetServa\Cms\Filament\Resources\PageResource\Schemas\PageFormSchemas;
use NetServa\Cms\Models\Page;
use UnitEnum;

/**
 * Filament Resource for CMS Pages
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Pages';

    protected static string|UnitEnum|null $navigationGroup = 'Cms';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        // Editor-only form - metadata accessed via modal buttons in page header
        return $schema->components(PageFormSchemas::getEditorSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('template')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'homepage' => 'success',
                        'service' => 'info',
                        'pricing' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->sortable()
                    ->date('M d, Y')
                    ->placeholder('-')
                    ->icon(fn (Page $record) => $record->is_published ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->iconColor(fn (Page $record) => $record->is_published ? 'success' : 'danger')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published')
                    ->placeholder('All pages')
                    ->trueLabel('Published only')
                    ->falseLabel('Drafts only'),

                Tables\Filters\SelectFilter::make('template')
                    ->options(config('netserva-cms.templates', [
                        'default' => 'Default',
                        'homepage' => 'Homepage',
                        'service' => 'Service Page',
                        'pricing' => 'Pricing',
                        'blank' => 'Blank',
                    ])),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit page'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete page'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
