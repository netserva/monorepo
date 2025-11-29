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
use NetServa\Cms\Filament\Resources\PostResource\Pages;
use NetServa\Cms\Filament\Resources\PostResource\Schemas\PostFormSchemas;
use NetServa\Cms\Models\Post;
use UnitEnum;

/**
 * Filament Resource for CMS Posts
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Posts';

    protected static string|UnitEnum|null $navigationGroup = 'Cms';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        // Editor-only form - metadata accessed via modal buttons in page header
        return $schema->components(PostFormSchemas::getEditorSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholders/post-placeholder.webp'))
                    ->getStateUsing(fn (Post $record) => $record->getFirstMediaUrl('featured_image') ?: null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->sortable()
                    ->date('M d, Y')
                    ->placeholder('-')
                    ->icon(fn (Post $record) => $record->is_published ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->iconColor(fn (Post $record) => $record->is_published ? 'success' : 'danger')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('word_count')
                    ->label('Words')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                // Hidden by default - toggle via column picker
                Tables\Columns\TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->color('success')
                    ->separator(',')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published')
                    ->placeholder('All posts')
                    ->trueLabel('Published only')
                    ->falseLabel('Drafts only'),

                Tables\Filters\SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit post'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete post'),
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
