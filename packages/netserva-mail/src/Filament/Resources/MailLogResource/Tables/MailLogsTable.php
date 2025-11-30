<?php

namespace NetServa\Mail\Filament\Resources\MailLogResource\Tables;

use Filament\Tables\Table;

class MailLogsTable
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
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([])
            ->paginated([5, 10, 25, 50, 100]);
    }
}
