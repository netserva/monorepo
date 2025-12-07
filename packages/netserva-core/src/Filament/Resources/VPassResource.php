<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\VPassResource\Pages;
use NetServa\Core\Filament\Resources\VPassResource\Tables\VPassTable;
use NetServa\Core\Models\VPass;

class VPassResource extends Resource
{
    protected static ?string $model = VPass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Credentials';

    protected static ?string $modelLabel = 'Credential';

    protected static ?string $pluralModelLabel = 'Credentials';

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getFormSchema(): array
    {
        return VPassTable::getFormSchema();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return VPassTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVPass::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'service', 'username'];
    }
}
