<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use NetServa\Core\Filament\Resources\AuditLogResource\Pages;
use NetServa\Core\Filament\Resources\AuditLogResource\Tables\AuditLogsTable;
use NetServa\Core\Models\AuditLog;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static string|\UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return AuditLogsTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Audit logs are created automatically by the system
    }

    public static function canEdit($record): bool
    {
        return false; // Audit logs cannot be edited
    }

    public static function canDelete($record): bool
    {
        return false; // Audit logs cannot be deleted manually
    }
}
