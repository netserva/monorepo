<?php

namespace NetServa\Mail\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use NetServa\Mail\Filament\Clusters\Mail\MailCluster;
use NetServa\Mail\Filament\Resources\MailServerResource\Pages\CreateMailServer;
use NetServa\Mail\Filament\Resources\MailServerResource\Pages\EditMailServer;
use NetServa\Mail\Filament\Resources\MailServerResource\Pages\ListMailServers;
use NetServa\Mail\Filament\Resources\MailServerResource\Schemas\MailServerForm;
use NetServa\Mail\Filament\Resources\MailServerResource\Tables\MailServersTable;
use NetServa\Mail\Models\MailServer;

class MailServerResource extends Resource
{
    protected static ?string $model = MailServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $cluster = MailCluster::class;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return MailServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailServersTable::configure($table);
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
            'index' => ListMailServers::route('/'),
            'create' => CreateMailServer::route('/create'),
            'edit' => EditMailServer::route('/{record}/edit'),
        ];
    }
}
