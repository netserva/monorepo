<?php

namespace NetServa\Core\Filament\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Validation\Rules\DomainRules;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Reusable Migration Form Components
 *
 * Provides standardized form components for migration-related forms.
 */
class MigrationFormComponents
{
    /**
     * Source server select
     */
    public static function sourceServerSelect(): Select
    {
        return Select::make('source_server')
            ->label('Source Server')
            ->options(fn () => SshHost::where('is_active', true)
                ->pluck('description', 'host')
                ->toArray())
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Server to migrate FROM')
            ->rules(VhostRules::vnodeExists())
            ->suffixIcon('heroicon-o-arrow-right-circle')
            ->live();
    }

    /**
     * Target server select
     */
    public static function targetServerSelect(): Select
    {
        return Select::make('target_server')
            ->label('Target Server')
            ->options(fn () => SshHost::where('is_active', true)
                ->pluck('description', 'host')
                ->toArray())
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Server to migrate TO')
            ->rules(VhostRules::vnodeExists())
            ->different('source_server')
            ->suffixIcon('heroicon-o-check-circle')
            ->live();
    }

    /**
     * Domain input for migration
     */
    public static function domainInput(): TextInput
    {
        return TextInput::make('domain')
            ->label('Domain to Migrate')
            ->required()
            ->rules(DomainRules::domain())
            ->placeholder('example.com')
            ->helperText('Domain/vhost to migrate between servers')
            ->maxLength(255)
            ->suffixIcon('heroicon-o-globe-alt');
    }

    /**
     * Job name input
     */
    public static function jobNameInput(): TextInput
    {
        return TextInput::make('job_name')
            ->label('Job Name')
            ->required()
            ->placeholder('Migration: example.com')
            ->helperText('Descriptive name for this migration job')
            ->maxLength(255)
            ->default(fn ($get) => 'Migration: '.($get('domain') ?? 'New Job'));
    }

    /**
     * Migration type select
     */
    public static function migrationTypeSelect(): Select
    {
        return Select::make('migration_type')
            ->label('Migration Type')
            ->options([
                'full' => 'Full Migration (Database + Files + Config)',
                'database-only' => 'Database Only',
                'files-only' => 'Files Only',
                'config-only' => 'Configuration Only',
            ])
            ->default('full')
            ->required()
            ->helperText('Select which components to migrate')
            ->live()
            ->suffixIcon('heroicon-o-cog');
    }

    /**
     * Description textarea
     */
    public static function descriptionTextarea(): Textarea
    {
        return Textarea::make('description')
            ->label('Description')
            ->placeholder('Additional notes about this migration...')
            ->rows(3)
            ->maxLength(1000)
            ->columnSpanFull();
    }

    /**
     * Dry run toggle
     */
    public static function dryRunToggle(): Toggle
    {
        return Toggle::make('dry_run')
            ->label('Dry Run')
            ->default(true)
            ->helperText('Simulate migration without making actual changes')
            ->inline(false);
    }

    /**
     * Backup toggle
     */
    public static function backupToggle(): Toggle
    {
        return Toggle::make('step_backup')
            ->label('Create Backup')
            ->default(true)
            ->helperText('Backup data before migration')
            ->inline(false);
    }

    /**
     * Cleanup toggle
     */
    public static function cleanupToggle(): Toggle
    {
        return Toggle::make('step_cleanup')
            ->label('Cleanup After Migration')
            ->default(true)
            ->helperText('Remove temporary files after successful migration')
            ->inline(false);
    }

    /**
     * SSH host relationship select
     */
    public static function sshHostSelect(): Select
    {
        return Select::make('ssh_host_id')
            ->label('SSH Host')
            ->relationship('sshHost', 'host')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->host} - {$record->description}")
            ->searchable()
            ->preload()
            ->helperText('Associated SSH host configuration (optional)');
    }

    /**
     * Complete migration options section
     */
    public static function migrationOptionsSection(): Section
    {
        return Section::make('Migration Options')
            ->description('Configure migration behavior')
            ->schema([
                self::migrationTypeSelect(),
                self::dryRunToggle(),
                self::backupToggle(),
                self::cleanupToggle(),
            ])
            ->columns(2)
            ->collapsible();
    }

    /**
     * Complete server selection section
     */
    public static function serverSelectionSection(): Section
    {
        return Section::make('Server Configuration')
            ->description('Select source and destination servers')
            ->schema([
                self::sourceServerSelect(),
                self::targetServerSelect(),
                self::sshHostSelect(),
            ])
            ->columns(3);
    }
}
