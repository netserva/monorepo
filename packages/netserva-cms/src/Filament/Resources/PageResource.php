<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use NetServa\Cms\Filament\Resources\PageResource\Pages;
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Pages';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', \Illuminate\Support\Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Page::class, 'slug', ignoreRecord: true)
                            ->helperText('URL-friendly version of the title'),

                        Forms\Components\Select::make('template')
                            ->required()
                            ->options(config('netserva-cms.templates', [
                                'default' => 'Default',
                                'homepage' => 'Homepage',
                                'service' => 'Service Page',
                                'pricing' => 'Pricing',
                                'blank' => 'Blank',
                            ]))
                            ->default('default'),

                        Forms\Components\RichEditor::make('content')
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('attachments'),

                        Forms\Components\Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Short description for listings and SEO'),
                    ])
                    ->columns(2),

                Section::make('Hierarchy')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Page')
                            ->relationship('parent', 'title')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty for top-level pages'),

                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order in navigation (lower numbers first)'),
                    ])
                    ->columns(2),

                Section::make('Publishing')
                    ->schema([
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(false)
                            ->helperText('Make this page visible on the website'),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->helperText('Schedule publication for a future date'),
                    ])
                    ->columns(2),

                Section::make('SEO & Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->helperText('SEO title (leave empty to use page title)'),

                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('SEO description (leave empty to use excerpt)'),

                        Forms\Components\TextInput::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->helperText('Comma-separated keywords'),

                        Forms\Components\TextInput::make('og_image')
                            ->label('Open Graph Image URL')
                            ->helperText('URL for social media sharing image'),

                        Forms\Components\Select::make('twitter_card')
                            ->label('Twitter Card Type')
                            ->options([
                                'summary' => 'Summary',
                                'summary_large_image' => 'Summary Large Image',
                                'app' => 'App',
                                'player' => 'Player',
                            ])
                            ->default('summary_large_image')
                            ->helperText('Twitter card display type'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Media')
                    ->schema([
                        Forms\Components\SpatieMediaLibraryFileUpload::make('featured_image')
                            ->collection('featured_image')
                            ->image()
                            ->helperText('Main image for this page'),

                        Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->image()
                            ->helperText('Additional images'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Page $record): string => $record->slug),

                Tables\Columns\TextColumn::make('template')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'homepage' => 'success',
                        'service' => 'info',
                        'pricing' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publish Date')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order')
                    ->sortable()
                    ->toggleable(),

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
                    ->options(config('netserva-cms.templates')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['parent']);
    }
}
