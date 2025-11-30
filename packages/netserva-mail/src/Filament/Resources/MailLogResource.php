<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailLogResource\Pages\ListMailLogs;
use NetServa\Mail\Filament\Resources\MailLogResource\Tables\MailLogsTable;
use NetServa\Mail\Models\MailLog;
use UnitEnum;

class MailLogResource extends Resource
{
    protected static ?string $model = MailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return MailLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailLogs::route('/'),
        ];
    }
}
