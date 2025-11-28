<?php

namespace NetServa\Web\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Web\Filament\Clusters\Web\WebCluster;
use NetServa\Web\Filament\Resources\SslCertificateResource\Pages\CreateSslCertificate;
use NetServa\Web\Filament\Resources\SslCertificateResource\Pages\EditSslCertificate;
use NetServa\Web\Filament\Resources\SslCertificateResource\Pages\ListSslCertificates;
use NetServa\Web\Filament\Resources\SslCertificateResource\Schemas\SslCertificateForm;
use NetServa\Web\Filament\Resources\SslCertificateResource\Tables\SslCertificatesTable;
use NetServa\Web\Models\SslCertificate;

class SslCertificateResource extends Resource
{
    protected static ?string $model = SslCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $cluster = WebCluster::class;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return SslCertificateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SslCertificatesTable::configure($table);
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
            'index' => ListSslCertificates::route('/'),
            'create' => CreateSslCertificate::route('/create'),
            'edit' => EditSslCertificate::route('/{record}/edit'),
        ];
    }
}
