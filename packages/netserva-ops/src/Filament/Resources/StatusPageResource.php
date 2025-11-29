<?php

namespace NetServa\Ops\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Ops\Filament\Resources\StatusPageResource\Pages\CreateStatusPage;
use NetServa\Ops\Filament\Resources\StatusPageResource\Pages\EditStatusPage;
use NetServa\Ops\Filament\Resources\StatusPageResource\Pages\ListStatusPages;
use NetServa\Ops\Filament\Resources\StatusPageResource\Schemas\StatusPageForm;
use NetServa\Ops\Filament\Resources\StatusPageResource\Tables\StatusPagesTable;
use NetServa\Ops\Models\StatusPage;
use UnitEnum;

class StatusPageResource extends Resource
{
    protected static ?string $model = StatusPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return StatusPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatusPagesTable::configure($table);
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
            'index' => ListStatusPages::route('/'),
            'create' => CreateStatusPage::route('/create'),
            'edit' => EditStatusPage::route('/{record}/edit'),
        ];
    }
}
