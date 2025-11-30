<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailServerResource\Pages\ListMailServers;
use NetServa\Mail\Filament\Resources\MailServerResource\Tables\MailServersTable;
use NetServa\Mail\Models\MailServer;
use UnitEnum;

class MailServerResource extends Resource
{
    protected static ?string $model = MailServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 4;

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Mail Server 1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Display name for this mail server'),

                Forms\Components\TextInput::make('hostname')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., mail.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Fully qualified domain name of the mail server'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('infrastructure_node_id')
                    ->label('Infrastructure Node')
                    ->required()
                    ->relationship('infrastructureNode', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Physical or virtual server hosting this mail server'),

                Forms\Components\Select::make('server_type')
                    ->required()
                    ->options([
                        'postfix_dovecot' => 'Postfix + Dovecot',
                        'exim_dovecot' => 'Exim + Dovecot',
                        'sendmail_courier' => 'Sendmail + Courier',
                        'custom' => 'Custom',
                    ])
                    ->default('postfix_dovecot')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Mail server software stack'),
            ]),

            Grid::make(3)->schema([
                Forms\Components\TextInput::make('public_ip')
                    ->label('Public IP')
                    ->placeholder('e.g., 192.0.2.1')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Public IP address for this mail server'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Whether this mail server is currently active'),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary')
                    ->default(false)
                    ->inline(false)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Primary mail server for this infrastructure'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this mail server'),

            Grid::make(3)->schema([
                Forms\Components\TextInput::make('smtp_port')
                    ->label('SMTP Port')
                    ->numeric()
                    ->default(25)
                    ->placeholder('25')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('SMTP service port (default: 25)'),

                Forms\Components\TextInput::make('imap_port')
                    ->label('IMAP Port')
                    ->numeric()
                    ->default(143)
                    ->placeholder('143')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('IMAP service port (default: 143)'),

                Forms\Components\TextInput::make('pop3_port')
                    ->label('POP3 Port')
                    ->numeric()
                    ->default(110)
                    ->placeholder('110')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('POP3 service port (default: 110)'),
            ]),

            Grid::make(3)->schema([
                Forms\Components\Toggle::make('enable_ssl')
                    ->label('Enable SSL/TLS')
                    ->default(true)
                    ->inline(false)
                    ->live()
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Enable SSL/TLS encryption'),

                Forms\Components\TextInput::make('ssl_cert_path')
                    ->label('SSL Certificate Path')
                    ->placeholder('/etc/ssl/certs/mail.crt')
                    ->visible(fn ($get) => $get('enable_ssl'))
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Path to SSL certificate file'),

                Forms\Components\TextInput::make('ssl_key_path')
                    ->label('SSL Key Path')
                    ->placeholder('/etc/ssl/private/mail.key')
                    ->visible(fn ($get) => $get('enable_ssl'))
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Path to SSL private key file'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'healthy' => 'Healthy',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'maintenance' => 'Maintenance',
                        'unknown' => 'Unknown',
                    ])
                    ->default('unknown')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current health status of the mail server'),

                Forms\Components\TextInput::make('created_by')
                    ->maxLength(255)
                    ->placeholder('e.g., admin')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('User who created this mail server'),
            ]),

            Forms\Components\TagsInput::make('tags')
                ->placeholder('Add tags...')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Tags for organizing mail servers'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return MailServersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailServers::route('/'),
        ];
    }
}
