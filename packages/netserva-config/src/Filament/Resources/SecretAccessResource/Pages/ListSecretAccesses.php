<?php

namespace NetServa\Config\Filament\Resources\SecretAccessResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\SecretAccessResource;

class ListSecretAccesses extends ListRecords
{
    protected static string $resource = SecretAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
