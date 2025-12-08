<?php

declare(strict_types=1);

namespace NetServa\Crm\Filament\Resources\CrmClientResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use NetServa\Crm\Filament\Resources\CrmClientResource;
use NetServa\Crm\Models\CrmClient;

class ManageCrmClients extends ManageRecords
{
    protected static string $resource = CrmClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false)
                ->modalFooterActionsAlignment('end')
                ->modalSubmitActionLabel('Create')
                ->modalCancelActionLabel('Cancel')
                ->mutateFormDataUsing(function (array $data): array {
                    if (empty($data['name'])) {
                        // Name is always first + last name, never company
                        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                        $data['name'] = $name ?: ($data['first_name'] ?? $data['last_name'] ?? 'Unknown');
                    }

                    return $data;
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Clients';
    }

    public function getHeading(): string
    {
        $stats = $this->getClientStats();

        return "Clients ({$stats['active']} active, {$stats['total']} total)";
    }

    protected function getClientStats(): array
    {
        return [
            'total' => CrmClient::count(),
            'active' => CrmClient::active()->count(),
        ];
    }
}
