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
        // Map type-specific form field to database 'value' column
        $data['value'] = match ($data['type'] ?? 'string') {
            'string' => $data['value_string'] ?? '',
            'integer' => $data['value_integer'] ?? 0,
            'boolean' => ($data['value_boolean'] ?? false) ? '1' : '0',
            'json' => $data['value_json'] ?? '{}',
            default => $data['value_string'] ?? '',
        };

        // Clean up temporary typed fields
        unset($data['value_string'], $data['value_integer'], $data['value_boolean'], $data['value_json']);

        return $data;
    }
}
