<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages\CreateSslCertificateDeployment;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages\EditSslCertificateDeployment;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages\ListSslCertificateDeployments;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Schemas\SslCertificateDeploymentForm;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Tables\SslCertificateDeploymentsTable;
use NetServa\Web\Models\SslCertificateDeployment;
use UnitEnum;

class SslCertificateDeploymentResource extends Resource
{
    protected static ?string $model = SslCertificateDeployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return SslCertificateDeploymentForm::configure($schema);
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
            'create' => CreateSslCertificateDeployment::route('/create'),
            'edit' => EditSslCertificateDeployment::route('/{record}/edit'),
        ];
    }
}
