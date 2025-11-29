<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SshHostResource;

class EditSshHost extends EditRecord
{
    protected static string $resource = SshHostResource::class;

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
