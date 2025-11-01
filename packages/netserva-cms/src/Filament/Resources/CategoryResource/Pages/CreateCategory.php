<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\CategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\CategoryResource;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
