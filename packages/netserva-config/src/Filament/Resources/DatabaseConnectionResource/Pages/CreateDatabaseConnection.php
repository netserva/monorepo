<?php

namespace NetServa\Config\Filament\Resources\DatabaseConnectionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource;

class CreateDatabaseConnection extends CreateRecord
{
    protected static string $resource = DatabaseConnectionResource::class;
}
