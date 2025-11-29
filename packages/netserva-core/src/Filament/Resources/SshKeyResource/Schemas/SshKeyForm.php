<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SshKeyForm
{
    public static function make(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Key Information')
                    ->tabs([
                        Tab::make('Basic')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->unique(ignorable: fn ($record) => $record)
                                            ->maxLength(255)
                                            ->placeholder('e.g., lan, wan, github')
                                            ->helperText('Key filename (without extension)'),

                                        Select::make('type')
                                            ->required()
                                            ->options([
                                                'ed25519' => 'ED25519 (Recommended)',
                                                'rsa' => 'RSA',
                                                'ecdsa' => 'ECDSA',
                                            ])
                                            ->default('ed25519')
                                            ->helperText('ED25519 is modern and secure'),

                                        TextInput::make('comment')
                                            ->maxLength(255)
                                            ->placeholder('user@hostname')
                                            ->helperText('Key comment (typically user@host)'),

                                        TextInput::make('key_size')
                                            ->numeric()
                                            ->placeholder('4096')
                                            ->helperText('Only for RSA keys'),

                                        Toggle::make('has_passphrase')
                                            ->label('Has Passphrase')
                                            ->default(false),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Inactive keys not synced to filesystem'),
                                    ]),

                                Textarea::make('description')
                                    ->rows(2)
                                    ->placeholder('Purpose of this key...'),
                            ]),

                        Tab::make('Key Content')
                            ->schema([
                                Textarea::make('public_key')
                                    ->label('Public Key')
                                    ->rows(3)
                                    ->placeholder('ssh-ed25519 AAAAC3NzaC1...')
                                    ->helperText('Public key content (from .pub file)')
                                    ->columnSpanFull(),

                                Textarea::make('private_key')
                                    ->label('Private Key')
                                    ->rows(10)
                                    ->placeholder('-----BEGIN OPENSSH PRIVATE KEY-----...')
                                    ->helperText('Private key content (keep secret!)')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Metadata')
                            ->schema([
                                TextInput::make('fingerprint')
                                    ->disabled()
                                    ->placeholder('SHA256:...')
                                    ->helperText('Auto-generated from public key'),

                                TextInput::make('last_used_at')
                                    ->disabled()
                                    ->helperText('Last time this key was used'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
