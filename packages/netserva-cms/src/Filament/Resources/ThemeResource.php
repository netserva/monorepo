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

                // Theme Manifest (Read-Only Display)
                Section::make('Theme Manifest')
                    ->description('Theme configuration loaded from theme.json')
                    ->schema([
                        Forms\Components\Placeholder::make('manifest_info')
                            ->label('Manifest Information')
                            ->content(fn (?Theme $record) => $record
                                ? view('netserva-cms::filament.theme-manifest', ['theme' => $record])
                                : 'No manifest data available'
                            )
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
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
                Tables\Actions\Action::make('activate')
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

                Tables\Actions\Action::make('view_manifest')
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
                Tables\Actions\Action::make('discover')
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
