<?php

namespace NetServa\Web\Filament\Resources\SslCertificateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Web\Filament\Resources\SslCertificateResource;

class ListSslCertificates extends ListRecords
{
    protected static string $resource = SslCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
