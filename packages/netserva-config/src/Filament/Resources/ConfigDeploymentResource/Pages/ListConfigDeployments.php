<?php

namespace NetServa\Config\Filament\Resources\ConfigDeploymentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource;

class ListConfigDeployments extends ListRecords
{
    protected static string $resource = ConfigDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
