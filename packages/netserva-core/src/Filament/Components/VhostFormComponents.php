<?php

namespace NetServa\Core\Filament\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Validation\Rules\DomainRules;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Reusable VHost Form Components
 *
 * Provides standardized form components for VHost-related forms across
 * console commands and Filament resources.
 */
class VhostFormComponents
{
    /**
     * Server node (vnode) select field
     */
    public static function vnodeSelect(): Select
    {
        return Select::make('vnode')
            ->label('Server Node')
            ->relationship('sshHost', 'host')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->host} - {$record->description}")
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Select the target server node for this virtual host')
            ->rules(VhostRules::vnodeExists())
            ->live() // Make reactive for dependent fields
            ->afterStateUpdated(function ($state, callable $set) {
                // Auto-populate related fields based on server selection
            });
    }

    /**
     * Alternative vnode select without relationship (for commands)
     */
    public static function vnodeSelectSimple(): Select
    {
        return Select::make('vnode')
            ->label('Server Node')
            ->options(fn () => SshHost::where('is_active', true)
                ->pluck('description', 'host')
                ->toArray())
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Select the target server node')
            ->rules(VhostRules::vnodeExists());
    }

    /**
     * VHost domain input field
     */
    public static function vhostInput(?string $vnode = null): TextInput
    {
        $field = TextInput::make('vhost')
            ->label('Domain Name')
            ->required()
            ->rules(DomainRules::domain())
            ->placeholder('example.com')
            ->helperText('Enter the domain name for this virtual host')
            ->maxLength(255)
            ->suffixIcon('heroicon-o-globe-alt');

        // Add uniqueness validation if vnode is provided
        if ($vnode) {
            $field->rules(DomainRules::uniqueForVnode($vnode));
        }

        return $field;
    }

    /**
     * PHP version select field
     */
    public static function phpVersionSelect(): Select
    {
        return Select::make('php_version')
            ->label('PHP Version')
            ->options([
                '7.4' => 'PHP 7.4',
                '8.0' => 'PHP 8.0',
                '8.1' => 'PHP 8.1',
                '8.2' => 'PHP 8.2',
                '8.3' => 'PHP 8.3',
                '8.4' => 'PHP 8.4 (Recommended)',
            ])
            ->default('8.4')
            ->required()
            ->rules(VhostRules::phpVersion())
            ->helperText('Select the PHP version for this virtual host')
            ->searchable();
    }

    /**
     * SSL enabled toggle
     */
    public static function sslEnabledToggle(): Toggle
    {
        return Toggle::make('ssl_enabled')
            ->label('Enable SSL/TLS')
            ->default(true)
            ->helperText('Automatically provision and configure SSL certificate via Let\'s Encrypt')
            ->inline(false);
    }

    /**
     * Database type select
     */
    public static function databaseTypeSelect(): Select
    {
        return Select::make('database_type')
            ->label('Database Type')
            ->options([
                'sqlite' => 'SQLite (Recommended for small sites)',
                'mysql' => 'MySQL/MariaDB',
                'postgresql' => 'PostgreSQL',
            ])
            ->default('sqlite')
            ->required()
            ->rules(VhostRules::databaseType())
            ->helperText('Select the database engine')
            ->live()
            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                // Auto-generate database name based on domain
                if (! $get('database_name')) {
                    $vhost = $get('vhost');
                    if ($vhost) {
                        $dbName = str_replace(['.', '-'], '_', $vhost);
                        $set('database_name', $dbName);
                    }
                }
            });
    }

    /**
     * Database name input
     */
    public static function databaseNameInput(): TextInput
    {
        return TextInput::make('database_name')
            ->label('Database Name')
            ->placeholder('example_com')
            ->helperText('Leave empty to auto-generate from domain name')
            ->maxLength(64)
            ->rules(['nullable', 'string', 'alpha_dash', 'max:64']);
    }

    /**
     * Admin email input
     */
    public static function adminEmailInput(): TextInput
    {
        return TextInput::make('admin_email')
            ->label('Administrator Email')
            ->email()
            ->placeholder('admin@example.com')
            ->helperText('Email address for admin notifications and SSL certificates')
            ->maxLength(255)
            ->suffixIcon('heroicon-o-envelope');
    }

    /**
     * Webroot path input
     */
    public static function webrootInput(): TextInput
    {
        return TextInput::make('webroot')
            ->label('Web Document Root')
            ->placeholder('/srv/example.com/web')
            ->helperText('Leave empty to use default: /srv/{domain}/web')
            ->rules(VhostRules::filePathNullable())
            ->maxLength(4096)
            ->prefixIcon('heroicon-o-folder');
    }

    /**
     * Unix username input
     */
    public static function usernameInput(): TextInput
    {
        return TextInput::make('username')
            ->label('System Username')
            ->placeholder('u1001')
            ->helperText('Leave empty to auto-generate (u{uid})')
            ->rules(['nullable', ...VhostRules::unixUsername()])
            ->maxLength(32)
            ->alphaDash();
    }

    /**
     * Unix UID input
     */
    public static function uidInput(): TextInput
    {
        return TextInput::make('uid')
            ->label('User ID (UID)')
            ->numeric()
            ->placeholder('1001')
            ->helperText('Leave empty to auto-assign next available UID >= 1001')
            ->rules(['nullable', ...VhostRules::unixUid()])
            ->minValue(1000)
            ->maxValue(65535);
    }

    /**
     * Unix GID input
     */
    public static function gidInput(): TextInput
    {
        return TextInput::make('gid')
            ->label('Group ID (GID)')
            ->numeric()
            ->placeholder('1001')
            ->helperText('Leave empty to match UID')
            ->rules(['nullable', ...VhostRules::unixUid()])
            ->minValue(1000)
            ->maxValue(65535);
    }
}
