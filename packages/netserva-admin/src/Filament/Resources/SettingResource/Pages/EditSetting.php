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
        // Map database 'value' column to type-specific form field
        $value = $data['value'] ?? null;
        $type = $data['type'] ?? 'string';

        // Set the appropriate typed field based on type
        match ($type) {
            'string' => $data['value_string'] = $value,
            'integer' => $data['value_integer'] = $value,
            'boolean' => $data['value_boolean'] = $value,
            'json' => $data['value_json'] = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value,
            default => $data['value_string'] = $value,
        };

        // Don't pass 'value' to avoid hydration conflicts
        unset($data['value']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Map type-specific form field back to database 'value' column
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
