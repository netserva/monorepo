<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources\MailDomainResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class MailDomainForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make('Domain Information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Example Domain')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Display name for this mail domain'),

                    TextInput::make('domain')
                        ->label('Domain Name')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->maxLength(255)
                        ->placeholder('e.g., example.com')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Fully qualified domain name'),

                    Select::make('mail_server_id')
                        ->label('Mail Server')
                        ->relationship('mailServer', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Select mail server...')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Server that will handle mail for this domain'),

                    Textarea::make('description')
                        ->rows(2)
                        ->placeholder('Optional description for this mail domain'),
                ]),

            Section::make('Security Settings')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Toggle::make('enable_dkim')
                                ->label('DKIM')
                                ->default(true)
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('DomainKeys Identified Mail signing'),

                            Toggle::make('enable_spf')
                                ->label('SPF')
                                ->default(true)
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Sender Policy Framework records'),

                            Toggle::make('enable_dmarc')
                                ->label('DMARC')
                                ->default(true)
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('Domain-based Message Authentication'),
                        ]),
                ]),

            Section::make('Relay Configuration')
                ->schema([
                    Toggle::make('relay_enabled')
                        ->label('Enable Relay')
                        ->default(false)
                        ->live()
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Forward mail through external relay'),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('relay_host')
                                ->label('Relay Host')
                                ->maxLength(255)
                                ->placeholder('smtp.relay.example.com')
                                ->visible(fn ($get) => $get('relay_enabled'))
                                ->hintIcon('heroicon-o-question-mark-circle')
                                ->hintIconTooltip('SMTP relay hostname'),

                            TextInput::make('relay_port')
                                ->label('Relay Port')
                                ->numeric()
                                ->default(587)
                                ->minValue(1)
                                ->maxValue(65535)
                                ->visible(fn ($get) => $get('relay_enabled')),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),

            Section::make('Additional Settings')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Inactive domains will not accept mail'),

                    KeyValue::make('tags')
                        ->keyLabel('Tag')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Tag')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Custom tags for organization'),

                    KeyValue::make('metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Metadata')
                        ->hintIcon('heroicon-o-question-mark-circle')
                        ->hintIconTooltip('Additional metadata for this domain'),
                ])
                ->collapsible()
                ->collapsed(),
        ];
    }

    public static function make(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function configure(Schema $schema): Schema
    {
        return self::make($schema);
    }
}
