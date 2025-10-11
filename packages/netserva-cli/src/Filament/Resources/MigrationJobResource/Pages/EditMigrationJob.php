<?php

namespace NetServa\Cli\Filament\Resources\MigrationJobResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Cli\Filament\Resources\MigrationJobResource;

class EditMigrationJob extends EditRecord
{
    protected static string $resource = MigrationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
