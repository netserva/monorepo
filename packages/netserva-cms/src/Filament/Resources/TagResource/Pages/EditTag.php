<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\TagResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Cms\Filament\Resources\TagResource;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
