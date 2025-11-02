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
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Cms\Filament\Resources\CategoryResource\Pages;
use NetServa\Cms\Models\Category;
use UnitEnum;

/**
 * Filament Resource for CMS Categories
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Categories';

    protected static string|UnitEnum|null $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Row 1: Name, Slug, Type, Order (25% each)
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', \Illuminate\Support\Str::slug($state)))
                    ->columnSpan(1),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Category::class, 'slug', ignoreRecord: true)
                    ->helperText('URL-friendly version of the name')
                    ->columnSpan(1),

                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'post' => 'Post',
                        'portfolio' => 'Portfolio',
                        'news' => 'News',
                        'docs' => 'Documentation',
                    ])
                    ->default('post')
                    ->helperText('Type of content this category is for')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Order in category lists (lower numbers first)')
                    ->columnSpan(1),

                // Row 2: Description (full width)
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Brief description of this category'),
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Category $record): string => $record->slug),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'post' => 'info',
                        'portfolio' => 'success',
                        'news' => 'warning',
                        'docs' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('posts_count')
                    ->label('Posts')
                    ->counts('posts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'post' => 'Post',
                        'portfolio' => 'Portfolio',
                        'news' => 'News',
                        'docs' => 'Documentation',
                    ]),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
