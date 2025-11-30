<?php

namespace NetServa\Web\Filament\Resources\WebServerResource\Schemas;

use Filament\Schemas\Schema;

class WebServerForm
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
