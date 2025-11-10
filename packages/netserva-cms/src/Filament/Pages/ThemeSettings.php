<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;
use UnitEnum;

/**
 * Theme Settings Page
 *
 * Allows customization of the active theme's colors, typography, and layout
 */
class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Theme Settings';

    protected static string|UnitEnum|null $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 11;

    protected string $view = 'netserva-cms::filament.pages.theme-settings';

    public ?array $data = [];

    protected ?Theme $activeTheme = null;

    public function mount(): void
    {
        $this->activeTheme = app(ThemeService::class)->getActive();

        // Load current settings into form
        $this->form->fill($this->getFormData());
    }

    protected function getFormData(): array
    {
        $data = [];

        // Load color settings
        foreach ($this->activeTheme->colors() as $color) {
            $slug = $color['slug'];
            $data["color_{$slug}"] = $this->activeTheme->setting("colors.{$slug}", $color['value']);
        }

        // Load typography settings
        $typography = $this->activeTheme->typography();
        if (isset($typography['fonts']['heading']['family'])) {
            $data['font_heading'] = $this->activeTheme->setting('typography.fonts.heading.family', $typography['fonts']['heading']['family']);
        }
        if (isset($typography['fonts']['body']['family'])) {
            $data['font_body'] = $this->activeTheme->setting('typography.fonts.body.family', $typography['fonts']['body']['family']);
        }

        // Load layout settings
        $data['content_width'] = $this->activeTheme->setting('layout.contentWidth', '800px');
        $data['wide_width'] = $this->activeTheme->setting('layout.wideWidth', '1200px');

        return $data;
    }

    public function form(Form $form): Form
    {
        $colorInputs = [];
        foreach ($this->activeTheme->colors() as $color) {
            $slug = $color['slug'];
            $colorInputs[] = Forms\Components\ColorPicker::make("color_{$slug}")
                ->label($color['name'])
                ->helperText($color['description'] ?? "Default: {$color['value']}")
                ->default($color['value']);
        }

        $typography = $this->activeTheme->typography();

        return $form
            ->schema([
                Section::make('Colors')
                    ->description('Customize your theme color palette')
                    ->schema($colorInputs)
                    ->columns(2)
                    ->collapsible(),

                Section::make('Typography')
                    ->description('Customize fonts and text styling')
                    ->schema([
                        Forms\Components\TextInput::make('font_heading')
                            ->label('Heading Font')
                            ->helperText('Font family for headings')
                            ->default($typography['fonts']['heading']['family'] ?? 'Inter'),

                        Forms\Components\TextInput::make('font_body')
                            ->label('Body Font')
                            ->helperText('Font family for body text')
                            ->default($typography['fonts']['body']['family'] ?? 'system-ui'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Layout')
                    ->description('Customize layout dimensions')
                    ->schema([
                        Forms\Components\TextInput::make('content_width')
                            ->label('Content Width')
                            ->helperText('Maximum width for content (e.g., 800px, 50rem)')
                            ->default('800px'),

                        Forms\Components\TextInput::make('wide_width')
                            ->label('Wide Width')
                            ->helperText('Maximum width for wide content (e.g., 1200px, 75rem)')
                            ->default('1200px'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save'),

            Action::make('reset')
                ->label('Reset to Defaults')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reset Theme Settings')
                ->modalDescription('This will reset all customizations to the theme defaults from theme.json. This cannot be undone.')
                ->action(function () {
                    // Delete all settings for this theme
                    $this->activeTheme->settings()->delete();

                    // Clear cache
                    app(ThemeService::class)->clearCache();

                    // Reload form
                    $this->form->fill($this->getFormData());

                    Notification::make()
                        ->title('Settings Reset')
                        ->body('All customizations have been reset to theme defaults.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save color settings
        foreach ($this->activeTheme->colors() as $color) {
            $slug = $color['slug'];
            $key = "color_{$slug}";

            if (isset($data[$key])) {
                $this->activeTheme->setSetting("colors.{$slug}", $data[$key], 'colors');
            }
        }

        // Save typography settings
        if (isset($data['font_heading'])) {
            $this->activeTheme->setSetting('typography.fonts.heading.family', $data['font_heading'], 'typography');
        }
        if (isset($data['font_body'])) {
            $this->activeTheme->setSetting('typography.fonts.body.family', $data['font_body'], 'typography');
        }

        // Save layout settings
        if (isset($data['content_width'])) {
            $this->activeTheme->setSetting('layout.contentWidth', $data['content_width'], 'layout');
        }
        if (isset($data['wide_width'])) {
            $this->activeTheme->setSetting('layout.wideWidth', $data['wide_width'], 'layout');
        }

        // Clear theme cache
        app(ThemeService::class)->clearCache();

        Notification::make()
            ->title('Settings Saved')
            ->body('Theme settings have been updated successfully.')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return "Customize {$this->activeTheme->display_name}";
    }

    public function getHeading(): string
    {
        return "Theme Settings: {$this->activeTheme->display_name}";
    }

    public function getSubheading(): ?string
    {
        return 'Customize colors, typography, and layout for the active theme. Changes are applied immediately to your site.';
    }
}
