<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailQueueResource\Pages\ListMailQueues;
use NetServa\Mail\Filament\Resources\MailQueueResource\Tables\MailQueuesTable;
use NetServa\Mail\Models\MailQueue;
use UnitEnum;

class MailQueueResource extends Resource
{
    protected static ?string $model = MailQueue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return MailQueuesTable::configure($table);
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
            'index' => ListMailQueues::route('/'),
        ];
    }
}
