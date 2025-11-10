<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
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
use NetServa\Cms\Filament\Resources\ThemeResource\Pages;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;
use UnitEnum;

/**
 * Filament Resource for CMS Themes
 *
 * Manages theme discovery, activation, and customization
 */
class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Themes';

    protected static string|UnitEnum|null $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Basic Information
                Section::make('Theme Information')
                    ->description('Basic theme metadata and configuration')
                    ->schema([
                        Forms\Components\TextInput::make('display_name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable theme name'),

                        Forms\Components\TextInput::make('name')
                            ->label('Theme Slug')
                            ->required()
                            ->unique(Theme::class, 'name', ignoreRecord: true)
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Unique identifier (cannot be changed after creation)'),

                        Forms\Components\TextInput::make('version')
                            ->label('Version')
                            ->default('1.0.0')
                            ->maxLength(255)
                            ->helperText('Theme version (semver format)'),

                        Forms\Components\TextInput::make('author')
                            ->label('Author')
                            ->maxLength(255)
                            ->helperText('Theme author name'),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Brief description of the theme'),

                        Forms\Components\Select::make('parent_theme')
                            ->label('Parent Theme')
                            ->relationship('parent', 'display_name')
                            ->searchable()
                            ->preload()
                            ->helperText('Parent theme for inheritance (child theme feature)'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(false)
                            ->disabled()
                            ->helperText('Use the "Activate" action to switch themes'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // Theme Manifest - Summary Stats
                Section::make('Theme Manifest')
                    ->description('Theme configuration loaded from theme.json')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('colors_count')
                                    ->label('Colors')
                                    ->content(fn (?Theme $record) => $record && $record->colors()
                                        ? count($record->colors())
                                        : 0
                                    ),

                                Forms\Components\Placeholder::make('templates_count')
                                    ->label('Templates')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->manifest['templates'])
                                        ? array_sum(array_map('count', $record->manifest['templates']))
                                        : 0
                                    ),

                                Forms\Components\Placeholder::make('features_count')
                                    ->label('Features')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->manifest['support'])
                                        ? count(array_filter($record->manifest['support']))
                                        : 0
                                    ),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Colors Section
                Section::make('Color Palette')
                    ->description('Theme color definitions')
                    ->schema([
                        Forms\Components\Placeholder::make('colors_display')
                            ->label('')
                            ->content(function (?Theme $record) {
                                if (! $record || empty($record->colors())) {
                                    return 'No colors defined';
                                }

                                $html = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">';
                                foreach ($record->colors() as $color) {
                                    $html .= '
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <div class="w-12 h-12 rounded-lg border-2 border-gray-300 dark:border-gray-600 flex-shrink-0" style="background-color: '.$color['value'].'"></div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$color['name'].'</p>
                                                <code class="text-xs text-gray-500 dark:text-gray-400">'.$color['value'].'</code>
                                            </div>
                                        </div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (?Theme $record) => $record && ! empty($record->colors())),

                // Typography Section
                Section::make('Typography')
                    ->description('Font configuration')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('heading_font')
                                    ->label('Heading Font')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->typography()['fonts']['heading'])
                                        ? $record->typography()['fonts']['heading']['family'].' (via '.ucfirst($record->typography()['fonts']['heading']['provider'] ?? 'system').')'
                                        : 'Not defined'
                                    ),

                                Forms\Components\Placeholder::make('body_font')
                                    ->label('Body Font')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->typography()['fonts']['body'])
                                        ? $record->typography()['fonts']['body']['family'].' (via '.ucfirst($record->typography()['fonts']['body']['provider'] ?? 'system').')'
                                        : 'Not defined'
                                    ),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (?Theme $record) => $record && ! empty($record->typography())),

                // Layout Section
                Section::make('Layout')
                    ->description('Layout dimensions and spacing')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('content_width')
                                    ->label('Content Width')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->manifest['settings']['layout']['contentWidth'])
                                        ? $record->manifest['settings']['layout']['contentWidth']
                                        : 'Not defined'
                                    ),

                                Forms\Components\Placeholder::make('wide_width')
                                    ->label('Wide Width')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->manifest['settings']['layout']['wideWidth'])
                                        ? $record->manifest['settings']['layout']['wideWidth']
                                        : 'Not defined'
                                    ),

                                Forms\Components\Placeholder::make('container_width')
                                    ->label('Container Width')
                                    ->content(fn (?Theme $record) => $record && ! empty($record->manifest['settings']['layout']['containerWidth'])
                                        ? $record->manifest['settings']['layout']['containerWidth']
                                        : 'Not defined'
                                    ),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (?Theme $record) => $record && ! empty($record->manifest['settings']['layout'])),

                // Templates Section
                Section::make('Templates')
                    ->description('Available theme templates')
                    ->schema([
                        Forms\Components\Placeholder::make('templates_display')
                            ->label('')
                            ->content(function (?Theme $record) {
                                if (! $record || empty($record->manifest['templates'])) {
                                    return 'No templates defined';
                                }

                                $html = '<div class="space-y-4">';
                                foreach ($record->manifest['templates'] as $type => $templates) {
                                    $html .= '
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">'.ucfirst($type).'</h4>
                                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">'.count($templates).'</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">';

                                    foreach ($templates as $template) {
                                        $html .= '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-100 rounded-md" title="'.($template['description'] ?? '').'">'.$template['label'].'</span>';
                                    }

                                    $html .= '
                                            </div>
                                        </div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (?Theme $record) => $record && ! empty($record->manifest['templates'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Theme')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Theme $record): string => $record->name),

                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent_theme')
                    ->label('Parent')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('is_active', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All themes')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('parent_theme')
                    ->label('Theme Type')
                    ->options([
                        'null' => 'Parent Themes',
                        'not_null' => 'Child Themes',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'null') {
                            return $query->whereNull('parent_theme');
                        }
                        if ($data['value'] === 'not_null') {
                            return $query->whereNotNull('parent_theme');
                        }
                    }),
            ])
            ->recordActions([
                Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate Theme')
                    ->modalDescription(fn (Theme $record) => "Are you sure you want to activate '{$record->display_name}'? This will deactivate the current theme.")
                    ->hidden(fn (Theme $record) => $record->is_active)
                    ->action(function (Theme $record) {
                        $service = app(ThemeService::class);
                        $service->activate($record->name);

                        \Filament\Notifications\Notification::make()
                            ->title('Theme Activated')
                            ->body("'{$record->display_name}' is now the active theme.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                Action::make('view_manifest')
                    ->label('View Manifest')
                    ->icon('heroicon-o-document-text')
                    ->modalContent(fn (Theme $record) => view('netserva-cms::filament.theme-manifest-modal', ['theme' => $record]))
                    ->modalSubmitActionLabel('Close')
                    ->modalCancelAction(false),

                DeleteAction::make()
                    ->hidden(fn (Theme $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure? This will remove the theme from the database (files remain).'),
            ])
            ->toolbarActions([
                Action::make('discover')
                    ->label('Discover Themes')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->action(function () {
                        $service = app(ThemeService::class);
                        $count = $service->discover();

                        \Filament\Notifications\Notification::make()
                            ->title('Theme Discovery Complete')
                            ->body("Found and registered {$count} theme(s) from filesystem.")
                            ->success()
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Prevent deleting active theme
                            if ($records->contains('is_active', true)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Delete Active Theme')
                                    ->body('Please activate a different theme first.')
                                    ->danger()
                                    ->send();

                                return false;
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
