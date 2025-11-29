<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\SshKeyResource\Pages;
use NetServa\Core\Filament\Resources\SshKeyResource\Schemas\SshKeyForm;
use NetServa\Core\Filament\Resources\SshKeyResource\Tables\SshKeysTable;
use NetServa\Core\Models\SshKey;

class SshKeyResource extends Resource
{
    protected static ?string $model = SshKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'SSH Keys';

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SshKeyForm::make($schema);
    }

    public static function table(Table $table): Table
    {
        return SshKeysTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSshKeys::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'comment', 'description'];
    }
}
