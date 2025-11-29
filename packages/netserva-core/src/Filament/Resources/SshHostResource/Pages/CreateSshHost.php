<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Core\Filament\Resources\SshHostResource;

class CreateSshHost extends CreateRecord
{
    protected static string $resource = SshHostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
