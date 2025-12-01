<?php

namespace NetServa\Web\Filament\Resources\SslCertificateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Web\Filament\Resources\SslCertificateResource;

class ListSslCertificates extends ListRecords
{
    protected static string $resource = SslCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => SslCertificateResource::getFormSchema()),
        ];
    }
}
