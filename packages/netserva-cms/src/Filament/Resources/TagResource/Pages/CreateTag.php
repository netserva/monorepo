<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\TagResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\TagResource;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
