<?php

namespace NetServa\Web\Filament\Resources\SslCertificateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Web\Filament\Resources\SslCertificateResource;

class EditSslCertificate extends EditRecord
{
    protected static string $resource = SslCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
