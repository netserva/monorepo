<?php

declare(strict_types=1);

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Resources\MailAliasResource\Pages\ListMailAliases;
use NetServa\Mail\Filament\Resources\MailAliasResource\Tables\MailAliasesTable;
use NetServa\Mail\Models\MailAlias;
use UnitEnum;

class MailAliasResource extends Resource
{
    protected static ?string $model = MailAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 3;

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('alias_email')
                ->label('Alias Email')
                ->required()
                ->email()
                ->maxLength(255)
                ->placeholder('e.g., info@example.com')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('The email address that will forward messages'),

            Forms\Components\TagsInput::make('destination_emails')
                ->label('Destination Emails')
                ->required()
                ->placeholder('Add destination email addresses')
                ->hintIcon('heroicon-o-question-mark-circle')
                ->hintIconTooltip('Email addresses that will receive forwarded messages'),

            Grid::make(2)->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Enable or disable this alias'),

                Forms\Components\TagsInput::make('tags')
                    ->label('Tags')
                    ->placeholder('Add tags')
                    ->hintIcon('heroicon-o-question-mark-circle')
                    ->hintIconTooltip('Optional tags for categorization'),
            ]),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->placeholder('Optional description for this alias'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return MailAliasesTable::configure($table);
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
            'index' => ListMailAliases::route('/'),
        ];
    }
}
