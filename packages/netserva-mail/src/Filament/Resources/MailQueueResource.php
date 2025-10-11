<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailQueueResource\Pages\CreateMailQueue;
use NetServa\Mail\Filament\Resources\MailQueueResource\Pages\EditMailQueue;
use NetServa\Mail\Filament\Resources\MailQueueResource\Pages\ListMailQueues;
use NetServa\Mail\Filament\Resources\MailQueueResource\Schemas\MailQueueForm;
use NetServa\Mail\Filament\Resources\MailQueueResource\Tables\MailQueuesTable;
use NetServa\Mail\Models\MailQueue;

class MailQueueResource extends Resource
{
    protected static ?string $model = MailQueue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Mail Services';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return MailQueueForm::configure($schema);
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
            'create' => CreateMailQueue::route('/create'),
            'edit' => EditMailQueue::route('/{record}/edit'),
        ];
    }
}
