<?php

namespace NetServa\Core\Filament\Resources\MountPointResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\MountPointResource;

class ListMountPoints extends ListRecords
{
    protected static string $resource = MountPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
