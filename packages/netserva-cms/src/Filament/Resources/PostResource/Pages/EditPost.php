<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Cms\Filament\Resources\PostResource;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
