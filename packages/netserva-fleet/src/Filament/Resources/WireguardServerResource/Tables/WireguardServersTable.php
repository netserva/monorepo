<?php

namespace NetServa\Fleet\Filament\Resources\WireguardServerResource\Tables;

use Filament\Tables\Table;

class WireguardServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->paginated([5, 10, 25, 50, 100]);
    }
}
