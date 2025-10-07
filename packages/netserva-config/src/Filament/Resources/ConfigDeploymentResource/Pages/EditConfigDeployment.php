<?php

namespace NetServa\Config\Filament\Resources\ConfigDeploymentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource;

class EditConfigDeployment extends EditRecord
{
    protected static string $resource = ConfigDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
