<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Core\Filament\Resources\SshKeyResource;

class CreateSshKey extends CreateRecord
{
    protected static string $resource = SshKeyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
