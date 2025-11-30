<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources\MailAliasResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Mail\Filament\Resources\MailAliasResource;

class ListMailAliases extends ListRecords
{
    protected static string $resource = MailAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => MailAliasResource::getFormSchema()),
        ];
    }
}
