<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SshKeyResource;

class EditSshKey extends EditRecord
{
    protected static string $resource = SshKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
