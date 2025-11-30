<?php

namespace NetServa\Fleet\Filament\Resources;

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
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Pages\ListWireguardPeers;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Schemas\WireguardPeerForm;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource\Tables\WireguardPeersTable;
use NetServa\Fleet\Models\WireguardPeer;
use UnitEnum;

class WireguardPeerResource extends Resource
{
    protected static ?string $model = WireguardPeer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 8;  // Alphabetical: Wireguard Peers

    public static function getFormSchema(): array
    {
        return WireguardPeerForm::getComponents();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return WireguardPeersTable::configure($table)
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit peer')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => self::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete peer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10, 25, 50, 100]);
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
            'index' => ListWireguardPeers::route('/'),
        ];
    }
}
