<?php

declare(strict_types=1);

namespace NetServa\Fleet\Filament\Resources\WireguardResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use NetServa\Fleet\Filament\Resources\WireguardResource;
use NetServa\Fleet\Filament\Resources\WireguardResource\Schemas\ServerForm;
use NetServa\Fleet\Services\WireguardKeyService;

class ListServers extends ListRecords
{
    protected static string $resource = WireguardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->label('New Server')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::Large)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => ServerForm::getFormSchema())
                ->mutateFormDataUsing(function (array $data): array {
                    // Generate keypair if not provided
                    if (empty($data['public_key'])) {
                        $keys = WireguardKeyService::generateKeyPair();
                        $data['public_key'] = $keys['public'];
                        $data['private_key_encrypted'] = encrypt($keys['private']);
                    }

                    return $data;
                }),
        ];
    }
}
