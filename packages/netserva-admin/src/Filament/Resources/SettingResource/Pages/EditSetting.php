<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Admin\Filament\Resources\SettingResource;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert value to string for form display
        if ($data['type'] === 'json' && is_array($data['value'])) {
            $data['value'] = json_encode($data['value'], JSON_PRETTY_PRINT);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cast value based on type
        $data['value'] = match ($data['type']) {
            'integer' => (int) $data['value'],
            'boolean' => (bool) $data['value'],
            'json' => is_string($data['value']) ? json_decode($data['value'], true) : $data['value'],
            default => (string) $data['value'],
        };

        return $data;
    }
}
