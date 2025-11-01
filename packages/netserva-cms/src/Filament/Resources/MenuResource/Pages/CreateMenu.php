<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\MenuResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\MenuResource;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
