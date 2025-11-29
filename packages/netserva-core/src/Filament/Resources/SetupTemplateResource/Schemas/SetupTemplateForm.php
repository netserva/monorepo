<?php

namespace NetServa\Core\Filament\Resources\SetupTemplateResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use NetServa\Core\Filament\Components\SetupFormComponents;

class SetupTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->description('Basic template configuration')
                    ->schema([
                        SetupFormComponents::templateNameInput(),
                        SetupFormComponents::templateDisplayNameInput(),
                        SetupFormComponents::templateCategorySelect(),
                        SetupFormComponents::isActiveToggle(),
                    ])
                    ->columns(2),

                Section::make('Description & Documentation')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Describe what this template does...')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Textarea::make('documentation')
                            ->label('Documentation')
                            ->placeholder('Additional setup notes, requirements, and instructions...')
                            ->rows(5)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Configuration')
                    ->description('Template components and compatibility')
                    ->schema([
                        Select::make('components')
                            ->label('Components')
                            ->multiple()
                            ->options([
                                'nginx' => 'Nginx Web Server',
                                'php-fpm' => 'PHP-FPM',
                                'mysql' => 'MySQL/MariaDB',
                                'postgresql' => 'PostgreSQL',
                                'sqlite' => 'SQLite',
                                'postfix' => 'Postfix Mail Server',
                                'dovecot' => 'Dovecot IMAP/POP3',
                                'powerdns' => 'PowerDNS',
                                'ssl' => 'SSL/TLS (Let\'s Encrypt)',
                                'fail2ban' => 'Fail2ban',
                                'ufw' => 'UFW Firewall',
                            ])
                            ->searchable()
                            ->preload()
                            ->helperText('Select components included in this template')
                            ->columnSpanFull(),

                        SetupFormComponents::supportedOsSelect(),
                    ]),

                Section::make('Template Defaults')
                    ->description('Default configuration values')
                    ->schema([
                        Textarea::make('default_configuration')
                            ->label('Default Configuration (JSON)')
                            ->placeholder('{"php_version": "8.4", "ssl_enabled": true}')
                            ->rows(5)
                            ->helperText('JSON object with default configuration values')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
