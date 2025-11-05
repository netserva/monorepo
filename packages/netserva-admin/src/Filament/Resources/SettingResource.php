<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use NetServa\Admin\Filament\Resources\SettingResource\Pages;
use NetServa\Admin\Filament\Resources\SettingResource\Schemas\SettingForm;
use NetServa\Admin\Filament\Resources\SettingResource\Tables\SettingsTable;
use NetServa\Core\Models\Setting;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('netserva-admin.navigation_group', 'Administration');
    }

    public static function form(Schema $schema): Schema
    {
        return SettingForm::make($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
