<?php

namespace NetServa\Core\Filament\Components;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use NetServa\Core\Models\SetupTemplate;
use NetServa\Core\Models\SshHost;

/**
 * Reusable Setup/Deployment Form Components
 *
 * Provides standardized form components for setup and deployment forms.
 */
class SetupFormComponents
{
    /**
     * Template select field
     */
    public static function templateSelect(): Select
    {
        return Select::make('template_id')
            ->label('Setup Template')
            ->relationship('template', 'display_name')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->display_name} - {$record->category}")
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Select the setup template to deploy')
            ->live()
            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                // Auto-populate job name and description from template
                if ($state) {
                    $template = SetupTemplate::find($state);
                    if ($template && ! $get('job_name')) {
                        $set('job_name', "Setup: {$template->display_name}");
                    }
                    if ($template && ! $get('description')) {
                        $set('description', $template->description);
                    }
                }
            });
    }

    /**
     * Target host select
     */
    public static function targetHostSelect(): Select
    {
        return Select::make('target_host')
            ->label('Target Server')
            ->options(fn () => SshHost::where('is_active', true)
                ->pluck('description', 'host')
                ->toArray())
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Server to deploy the template to')
            ->suffixIcon('heroicon-o-server');
    }

    /**
     * Job name input
     */
    public static function jobNameInput(): TextInput
    {
        return TextInput::make('job_name')
            ->label('Job Name')
            ->required()
            ->placeholder('Setup: LEMP Stack')
            ->helperText('Descriptive name for this deployment job')
            ->maxLength(255);
    }

    /**
     * Description textarea
     */
    public static function descriptionTextarea(): Textarea
    {
        return Textarea::make('description')
            ->label('Description')
            ->placeholder('Deployment notes and requirements...')
            ->rows(3)
            ->maxLength(1000)
            ->columnSpanFull();
    }

    /**
     * Configuration key-value pairs
     */
    public static function configurationKeyValue(): KeyValue
    {
        return KeyValue::make('configuration')
            ->label('Configuration Variables')
            ->helperText('Override template default values (optional)')
            ->keyLabel('Variable Name')
            ->valueLabel('Value')
            ->addActionLabel('Add Variable')
            ->reorderable()
            ->columnSpanFull();
    }

    /**
     * Dry run toggle
     */
    public static function dryRunToggle(): Toggle
    {
        return Toggle::make('dry_run')
            ->label('Dry Run (Preview Mode)')
            ->default(false)
            ->helperText('Simulate deployment without making actual changes')
            ->inline(false);
    }

    /**
     * Priority input
     */
    public static function priorityInput(): TextInput
    {
        return TextInput::make('priority')
            ->label('Priority')
            ->numeric()
            ->default(0)
            ->helperText('Higher priority jobs run first (0 = normal)')
            ->minValue(0)
            ->maxValue(100)
            ->suffixIcon('heroicon-o-flag');
    }

    /**
     * Template name input (for creating templates)
     */
    public static function templateNameInput(): TextInput
    {
        return TextInput::make('name')
            ->label('Template Name')
            ->required()
            ->unique(ignoreRecord: true)
            ->placeholder('lemp-stack')
            ->helperText('Unique identifier for this template (lowercase, hyphens)')
            ->maxLength(100)
            ->rules(['required', 'string', 'alpha_dash', 'max:100', 'lowercase']);
    }

    /**
     * Template display name input
     */
    public static function templateDisplayNameInput(): TextInput
    {
        return TextInput::make('display_name')
            ->label('Display Name')
            ->required()
            ->placeholder('LEMP Stack (Nginx + PHP + MySQL)')
            ->helperText('Human-friendly name for this template')
            ->maxLength(255);
    }

    /**
     * Template category select
     */
    public static function templateCategorySelect(): Select
    {
        return Select::make('category')
            ->label('Category')
            ->options([
                'web-server' => 'Web Server',
                'database' => 'Database',
                'mail-server' => 'Mail Server',
                'dns-server' => 'DNS Server',
                'monitoring' => 'Monitoring',
                'security' => 'Security',
                'development' => 'Development',
                'other' => 'Other',
            ])
            ->required()
            ->searchable()
            ->helperText('Categorize this template');
    }

    /**
     * Supported OS multi-select
     */
    public static function supportedOsSelect(): Select
    {
        return Select::make('supported_os')
            ->label('Supported Operating Systems')
            ->options([
                'alpine' => 'Alpine Linux',
                'debian' => 'Debian',
                'ubuntu' => 'Ubuntu',
                'arch' => 'Arch Linux',
            ])
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Which OS distributions support this template')
            ->columnSpanFull();
    }

    /**
     * Is active toggle
     */
    public static function isActiveToggle(): Toggle
    {
        return Toggle::make('is_active')
            ->label('Active')
            ->default(true)
            ->helperText('Only active templates are available for deployment')
            ->inline(false);
    }
}
