<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailboxResource\Pages\ListMailboxes;
use NetServa\Mail\Filament\Resources\MailboxResource\Schemas\MailboxForm;
use NetServa\Mail\Filament\Resources\MailboxResource\Tables\MailboxesTable;
use NetServa\Mail\Models\Mailbox;
use UnitEnum;

class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 2;

    public static function getFormSchema(): array
    {
        return MailboxForm::getSchema();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return MailboxesTable::configure($table)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit mailbox')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete mailbox'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
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
        ];
    }
}
