<?php

declare(strict_types=1);

namespace NetServa\Crm\Filament\Resources\CrmClientResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use NetServa\Crm\Filament\Resources\CrmClientResource;
use NetServa\Crm\Models\CrmClient;

class ManageCrmClients extends ManageRecords
{
    protected static string $resource = CrmClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    // Auto-populate name based on available fields
                    if (empty($data['name'])) {
                        if (! empty($data['company_name'])) {
                            $data['name'] = $data['company_name'];
                        } else {
                            $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                        }
                    }

                    return $data;
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
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
