<?php

namespace NetServa\Web\Filament\Resources\WebServerResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use NetServa\Web\Filament\Resources\WebServerResource;

class WebServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Edit web server')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->schema(fn () => WebServerResource::getFormSchema()),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete web server'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
