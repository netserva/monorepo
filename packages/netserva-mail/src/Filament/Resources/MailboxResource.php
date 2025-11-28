<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Clusters\Mail\MailCluster;
use NetServa\Mail\Filament\Resources\MailboxResource\Pages\CreateMailbox;
use NetServa\Mail\Filament\Resources\MailboxResource\Pages\EditMailbox;
use NetServa\Mail\Filament\Resources\MailboxResource\Pages\ListMailboxes;
use NetServa\Mail\Filament\Resources\MailboxResource\Schemas\MailboxForm;
use NetServa\Mail\Filament\Resources\MailboxResource\Tables\MailboxesTable;
use NetServa\Mail\Models\Mailbox;

class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $cluster = MailCluster::class;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return MailboxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailboxesTable::configure($table);
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
            'index' => ListMailboxes::route('/'),
            'create' => CreateMailbox::route('/create'),
            'edit' => EditMailbox::route('/{record}/edit'),
        ];
    }
}
