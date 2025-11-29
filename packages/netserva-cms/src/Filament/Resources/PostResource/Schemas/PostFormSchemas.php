<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PostResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Post;

/**
 * Shared form schemas for Post create/edit pages
 *
 * Used by modal actions in page headers for a clean, mobile-friendly UX
 */
class PostFormSchemas
{
    /**
     * Basic Information: Title, Slug, Author, Excerpt
     */
    public static function getBasicSchema(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                ->placeholder('Enter post title')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('The main title displayed on the post'),

            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(Post::class, 'slug', ignoreRecord: true)
                ->placeholder('auto-generated-from-title')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('URL-friendly version (auto-generated from title)'),

            Forms\Components\Select::make('author_id')
                ->label('Author')
                ->relationship('author', 'name')
                ->searchable()
                ->preload()
                ->default(fn () => auth()->id())
                ->required()
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Post author'),

            Forms\Components\Textarea::make('excerpt')
                ->rows(3)
                ->maxLength(500)
                ->placeholder('Brief summary for listings and search results')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Short description for listings and SEO'),
        ];
    }

    /**
     * SEO & Metadata: Meta title, description, keywords, social sharing
     */
    public static function getSeoSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('meta_title')
                    ->label('Meta Title')
                    ->maxLength(255)
                    ->placeholder('Leave empty to use post title')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('SEO title for search engines'),

                Forms\Components\TextInput::make('meta_keywords')
                    ->label('Meta Keywords')
                    ->placeholder('laravel, php, tutorial')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Comma-separated keywords'),
            ]),

            Forms\Components\Textarea::make('meta_description')
                ->label('Meta Description')
                ->rows(2)
                ->maxLength(500)
                ->placeholder('Leave empty to use excerpt')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('SEO description for search engines'),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('og_image')
                    ->label('Open Graph Image URL')
                    ->placeholder('https://example.com/image.jpg')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Image shown when shared on social media'),

                Forms\Components\Select::make('twitter_card')
                    ->label('Twitter Card Type')
                    ->options([
                        'summary' => 'Summary',
                        'summary_large_image' => 'Summary Large Image',
                        'app' => 'App',
                        'player' => 'Player',
                    ])
                    ->default('summary_large_image')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('How the post appears when shared on Twitter'),
            ]),
        ];
    }

    /**
     * Categories: Category and tag selection
     */
    public static function getCategoriesSchema(): array
    {
        return [
            Forms\Components\Select::make('categories')
                ->options(fn () => \NetServa\Cms\Models\Category::where('type', 'post')->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Hidden::make('type')
                        ->default('post'),
                ])
                ->createOptionUsing(fn (array $data) => \NetServa\Cms\Models\Category::create($data)->id)
                ->createOptionAction(fn (\Filament\Actions\Action $action) => $action
                    ->modalHeading('Create Category')
                    ->modalWidth('md')
                    ->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::End)
                    ->modalSubmitActionLabel('Submit'))
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Select or create categories'),

            Forms\Components\Select::make('tags')
                ->options(fn () => \NetServa\Cms\Models\Tag::pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data) => \NetServa\Cms\Models\Tag::create($data)->id)
                ->createOptionAction(fn (\Filament\Actions\Action $action) => $action
                    ->modalHeading('Create Tag')
                    ->modalWidth('md')
                    ->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::End)
                    ->modalSubmitActionLabel('Submit'))
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Select or create tags'),
        ];
    }

    /**
     * Publishing: Publish date, status, reading stats
     */
    public static function getPublishSchema(): array
    {
        return [
            Forms\Components\Toggle::make('is_published')
                ->label('Published')
                ->default(false)
                ->live()
                ->afterStateUpdated(function (bool $state, callable $set, callable $get) {
                    if ($state && ! $get('published_at')) {
                        $set('published_at', now());
                    }
                })
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Make this post visible on the website'),

            Forms\Components\DateTimePicker::make('published_at')
                ->label('Publish Date')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('When the post should be published'),

            Grid::make(2)->schema([
                Forms\Components\Placeholder::make('word_count')
                    ->label('Word Count')
                    ->content(fn (?Post $record): string => $record ? number_format($record->word_count) : '0'),

                Forms\Components\Placeholder::make('reading_time')
                    ->label('Reading Time')
                    ->content(fn (?Post $record): string => $record ? $record->getReadingTime().' min' : '0 min'),
            ]),
        ];
    }

    /**
     * Media: Featured image and gallery
     */
    public static function getMediaSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('featured_image')
                ->label('Featured Image')
                ->image()
                ->disk('public')
                ->directory('posts/featured')
                ->visibility('public')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Main image displayed with the post'),

            Forms\Components\FileUpload::make('gallery')
                ->label('Gallery')
                ->multiple()
                ->image()
                ->disk('public')
                ->directory('posts/gallery')
                ->visibility('public')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Additional images for the post'),
        ];
    }

    /**
     * Content Editor - standalone for the main form
     * Full default toolbar with floating toolbars for contextual extras
     */
    public static function getEditorSchema(): array
    {
        return [
            Forms\Components\RichEditor::make('content')
                ->required()
                ->hiddenLabel()
                // Default Filament v4 toolbar - full feature set
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                    ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                    ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                    ['table', 'attachFiles'],
                    ['undo', 'redo'],
                ])
                // Floating toolbars appear contextually when cursor is in specific nodes
                ->floatingToolbars([
                    'paragraph' => [
                        'bold', 'italic', 'underline', 'strike', 'link', 'textColor', 'highlight',
                    ],
                    'heading' => [
                        'h1', 'h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd',
                    ],
                    'table' => [
                        'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                        'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                        'tableMergeCells', 'tableSplitCell', 'tableToggleHeaderRow', 'tableDelete',
                    ],
                ])
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory('attachments')
                ->columnSpanFull(),
        ];
    }
}
