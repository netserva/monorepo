<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SshHostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SshHostResource;

class ListSshHosts extends ListRecords
{
    protected static string $resource = SshHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
