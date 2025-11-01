<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Cms\Filament\Resources\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
