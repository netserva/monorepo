<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PageResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Page;

/**
 * Shared form schemas for Page create/edit pages
 *
 * Used by modal actions in page headers for a clean, mobile-friendly UX
 */
class PageFormSchemas
{
    /**
     * Basic Information: Title, Slug, Template, Excerpt
     */
    public static function getBasicSchema(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                ->placeholder('Enter page title')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('The main title displayed on the page'),

            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(Page::class, 'slug', ignoreRecord: true)
                ->placeholder('auto-generated-from-title')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('URL-friendly version (auto-generated from title)'),

            Forms\Components\Select::make('template')
                ->required()
                ->options(config('netserva-cms.templates', [
                    'default' => 'Default',
                    'homepage' => 'Homepage',
                    'service' => 'Service Page',
                    'pricing' => 'Pricing',
                    'blank' => 'Blank',
                ]))
                ->default('default')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Page template/layout'),

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
                    ->placeholder('Leave empty to use page title')
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
                    ->hintIconTooltip('How the page appears when shared on Twitter'),
            ]),
        ];
    }

    /**
     * Hierarchy: Parent page and order
     */
    public static function getHierarchySchema(): array
    {
        return [
            Forms\Components\Select::make('parent_id')
                ->label('Parent Page')
                ->options(fn () => Page::whereNull('parent_id')->pluck('title', 'id'))
                ->searchable()
                ->preload()
                ->placeholder('None (top-level page)')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Leave empty for top-level pages'),

            Forms\Components\TextInput::make('order')
                ->numeric()
                ->default(0)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Order in navigation (lower numbers first)'),
        ];
    }

    /**
     * Publishing: Publish date and status
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
                ->hintIconTooltip('Make this page visible on the website'),

            Forms\Components\DateTimePicker::make('published_at')
                ->label('Publish Date')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('When the page should be published'),
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
                ->directory('pages/featured')
                ->visibility('public')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Main image displayed with the page'),

            Forms\Components\FileUpload::make('gallery')
                ->label('Gallery')
                ->multiple()
                ->image()
                ->disk('public')
                ->directory('pages/gallery')
                ->visibility('public')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Additional images for the page'),
        ];
    }

    /**
     * Content Editor - standalone for the main form
     */
    public static function getEditorSchema(): array
    {
        return [
            Forms\Components\RichEditor::make('content')
                ->required()
                ->hiddenLabel()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                    ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                    ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                    ['table', 'attachFiles'],
                    ['undo', 'redo'],
                ])
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
