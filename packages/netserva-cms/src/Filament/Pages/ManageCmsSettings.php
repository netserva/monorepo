<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Pages;

// Only define this page if Spatie Settings is available
if (! class_exists(\Filament\Pages\SettingsPage::class)) {
    return;
}

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Filament\Support\Icons\Heroicon;
use NetServa\Cms\Settings\CmsSettings;

/**
 * Manage CMS Settings Page
 *
 * Provides CRUD interface for CMS settings via Filament admin.
 * Only appears when netserva-core is installed (provides Spatie Settings).
 *
 * In standalone mode, settings are managed via config files + .env
 */
class ManageCmsSettings extends SettingsPage
{
    protected static string $settings = CmsSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'CMS Settings';

    protected static ?string $title = 'CMS Settings';

    protected static ?int $navigationSort = 10;

    /**
     * Only show this page if Spatie Settings is available
     * (i.e., netserva-core is installed)
     */
    public static function shouldRegisterNavigation(): bool
    {
        return class_exists(\Spatie\LaravelSettings\Settings::class)
            && class_exists(CmsSettings::class);
    }

    /**
     * Build the settings form
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Site Identity')
                ->description('Basic information about your site')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Site Name')
                        ->required()
                        ->maxLength(100)
                        ->helperText('The name of your site (e.g., "NetServa", "Acme Corp")'),

                    Forms\Components\TextInput::make('tagline')
                        ->label('Tagline')
                        ->maxLength(200)
                        ->helperText('Short description or slogan (e.g., "Server Management Platform")'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Longer description for SEO and about pages'),

                    Forms\Components\FileUpload::make('logo_url')
                        ->label('Logo')
                        ->image()
                        ->disk('public')
                        ->directory('branding')
                        ->imageEditor()
                        ->helperText('Upload your site logo (PNG, JPG, SVG)'),

                    Forms\Components\FileUpload::make('favicon_url')
                        ->label('Favicon')
                        ->image()
                        ->disk('public')
                        ->directory('branding')
                        ->helperText('Upload favicon (32x32 or 64x64 pixels)'),
                ]),

            Forms\Components\Section::make('Contact Information')
                ->description('How people can reach you')
                ->icon('heroicon-o-phone')
                ->schema([
                    Forms\Components\TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Primary contact email address'),

                    Forms\Components\TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->maxLength(50)
                        ->helperText('Contact phone number'),

                    Forms\Components\Textarea::make('contact_address')
                        ->label('Physical Address')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Physical mailing address'),
                ]),

            Forms\Components\Section::make('Localization')
                ->description('Regional settings')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    Forms\Components\Select::make('timezone')
                        ->label('Timezone')
                        ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                        ->searchable()
                        ->required()
                        ->helperText('Default timezone for dates and times'),

                    Forms\Components\Select::make('locale')
                        ->label('Language')
                        ->options([
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'it' => 'Italian',
                            'pt' => 'Portuguese',
                        ])
                        ->required()
                        ->helperText('Default language for the site'),
                ]),
        ]);
    }
}
