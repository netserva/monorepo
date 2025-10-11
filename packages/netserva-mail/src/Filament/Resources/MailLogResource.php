<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailLogResource\Pages\CreateMailLog;
use NetServa\Mail\Filament\Resources\MailLogResource\Pages\EditMailLog;
use NetServa\Mail\Filament\Resources\MailLogResource\Pages\ListMailLogs;
use NetServa\Mail\Filament\Resources\MailLogResource\Schemas\MailLogForm;
use NetServa\Mail\Filament\Resources\MailLogResource\Tables\MailLogsTable;
use NetServa\Mail\Models\MailLog;

class MailLogResource extends Resource
{
    protected static ?string $model = MailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Mail Services';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return MailLogForm::configure($schema);
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
            'create' => CreateMailLog::route('/create'),
            'edit' => EditMailLog::route('/{record}/edit'),
        ];
    }
}
