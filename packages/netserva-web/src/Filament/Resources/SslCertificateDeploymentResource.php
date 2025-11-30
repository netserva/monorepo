<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages\ListSslCertificateDeployments;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Tables\SslCertificateDeploymentsTable;
use NetServa\Web\Models\SslCertificateDeployment;
use UnitEnum;

class SslCertificateDeploymentResource extends Resource
{
    protected static ?string $model = SslCertificateDeployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 5;

    public static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('ssl_certificate_id')
                    ->label('SSL Certificate')
                    ->relationship('certificate', 'common_name')
                    ->searchable()
                    ->required()
                    ->preload()
                    ->placeholder('Select certificate')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('SSL certificate to deploy'),

                Forms\Components\Select::make('infrastructure_node_id')
                    ->label('Infrastructure Node')
                    ->relationship('infrastructureNode', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Select node (optional)')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Target infrastructure node'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('server_hostname')
                    ->label('Server Hostname')
                    ->maxLength(255)
                    ->placeholder('e.g., web01.example.com')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Hostname or IP address of target server'),

                Forms\Components\Select::make('service_type')
                    ->label('Service Type')
                    ->options(SslCertificateDeployment::SERVICE_TYPES)
                    ->required()
                    ->default('nginx')
                    ->placeholder('Select service')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Web server or service receiving the certificate'),
            ]),

            Grid::make(3)->schema([
                Forms\Components\TextInput::make('certificate_path')
                    ->label('Certificate Path')
                    ->maxLength(255)
                    ->placeholder('/etc/ssl/certs/cert.pem')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Full path where certificate will be stored'),

                Forms\Components\TextInput::make('private_key_path')
                    ->label('Private Key Path')
                    ->maxLength(255)
                    ->placeholder('/etc/ssl/private/key.pem')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Full path where private key will be stored'),

                Forms\Components\TextInput::make('certificate_chain_path')
                    ->label('Chain Path')
                    ->maxLength(255)
                    ->placeholder('/etc/ssl/certs/chain.pem')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Full path where certificate chain will be stored'),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('deployment_type')
                    ->label('Deployment Type')
                    ->options(SslCertificateDeployment::DEPLOYMENT_TYPES)
                    ->required()
                    ->default('new')
                    ->placeholder('Select type')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Type of deployment operation'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(SslCertificateDeployment::STATUSES)
                    ->required()
                    ->default('pending')
                    ->placeholder('Select status')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Current deployment status'),
            ]),

            Forms\Components\Textarea::make('deployment_errors')
                ->label('Deployment Errors')
                ->rows(3)
                ->placeholder('Error details (if any)')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('deployed_by')
                ->label('Deployed By')
                ->maxLength(255)
                ->placeholder('Username or system')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('User or system that initiated deployment'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return SslCertificateDeploymentsTable::configure($table);
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
            'index' => ListSslCertificateDeployments::route('/'),
        ];
    }
}
