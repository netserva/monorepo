<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use NetServa\Core\Models\SshKey;

class SshHostForm
{
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('host')
                ->label('Host Alias')
                ->required()
                ->unique(ignorable: fn ($record) => $record)
                ->maxLength(255)
                ->placeholder('e.g., mrn, nsorg, prod-web')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Short name used in SSH config and commands'),

            TextInput::make('hostname')
                ->label('Hostname / IP')
                ->required()
                ->maxLength(255)
                ->placeholder('192.168.1.100 or server.example.com')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('IP address or fully qualified domain name'),

            Grid::make(2)
                ->schema([
                    TextInput::make('port')
                        ->numeric()
                        ->default(22)
                        ->minValue(1)
                        ->maxValue(65535)
                        ->required(),

                    TextInput::make('user')
                        ->default('root')
                        ->maxLength(255)
                        ->required(),
                ]),

            Select::make('identity_file')
                ->label('SSH Key')
                ->options(fn () => SshKey::active()
                    ->pluck('name')
                    ->mapWithKeys(fn ($name) => ["~/.ssh/keys/{$name}" => $name])
                    ->toArray())
                ->searchable()
                ->placeholder('Select SSH key...')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Key from ~/.ssh/keys/'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Inactive hosts are not synced to ~/.ssh/hosts/'),

            TextInput::make('jump_host')
                ->label('Jump Host (ProxyJump)')
                ->placeholder('bastion')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('SSH alias to use as jump host'),

            Textarea::make('proxy_command')
                ->label('Proxy Command')
                ->rows(2)
                ->placeholder('ssh -W %h:%p bastion')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Custom proxy command (advanced)'),

            KeyValue::make('custom_options')
                ->label('Custom SSH Options')
                ->keyLabel('Option')
                ->valueLabel('Value')
                ->addActionLabel('Add Option')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Additional SSH config options'),

            Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this host'),
        ];
    }

    public static function make(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }
}
