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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Categories';

    protected static string|UnitEnum|null $navigationGroup = 'Cms';

    protected static ?int $navigationSort = 4;

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                    ->placeholder('e.g., News')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display name for this category'),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Category::class, 'slug', ignoreRecord: true)
                    ->placeholder('e.g., news')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('URL-friendly identifier (auto-generated from name)'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'post' => 'Post',
                        'portfolio' => 'Portfolio',
                        'news' => 'News',
                        'docs' => 'Documentation',
                    ])
                    ->default('post')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Content type this category applies to'),

                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->placeholder('0')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display order (lower numbers appear first)'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this category'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

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

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'post' => 'Post',
                        'portfolio' => 'Portfolio',
                        'news' => 'News',
                        'docs' => 'Documentation',
                    ]),
            ])
            ->searchable(false)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit category')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete category'),
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
            'index' => Pages\ListCategories::route('/'),
        ];
    }
}
