<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshKeyResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SshKeyResource;

class ListSshKeys extends ListRecords
{
    protected static string $resource = SshKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
