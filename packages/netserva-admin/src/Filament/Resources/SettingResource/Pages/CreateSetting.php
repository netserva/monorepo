<?php

declare(strict_types=1);

namespace NetServa\Admin\Filament\Resources\SettingResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use NetServa\Admin\Filament\Resources\SettingResource;

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
