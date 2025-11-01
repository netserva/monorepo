<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\PageResource;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
