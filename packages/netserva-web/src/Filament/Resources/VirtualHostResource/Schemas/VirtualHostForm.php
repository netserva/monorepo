<?php

namespace NetServa\Web\Filament\Resources\VirtualHostResource\Schemas;

use Filament\Schemas\Schema;

class VirtualHostForm
{
    public static function getSchema(): array
    {
        return [
            //
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getSchema());
    }
}
