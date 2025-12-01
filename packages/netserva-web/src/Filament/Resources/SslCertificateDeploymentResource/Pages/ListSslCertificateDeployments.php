<?php

namespace NetServa\Web\Filament\Resources\SslCertificateDeploymentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource;

class ListSslCertificateDeployments extends ListRecords
{
    protected static string $resource = SslCertificateDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => SslCertificateDeploymentResource::getFormSchema()),
        ];
    }
}
