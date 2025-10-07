<?php

namespace NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource;

class EditSslCertificateDeployment extends EditRecord
{
    protected static string $resource = SslCertificateDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
