<?php

declare(strict_types=1);

namespace NetServa\Core\Filament\Resources\SettingResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Core\Filament\Resources\SettingResource;
use NetServa\Core\Filament\Resources\SettingResource\Schemas\SettingForm;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => SettingForm::getFormSchema())
                ->mutateFormDataBeforeCreate(function (array $data): array {
                    $data['value'] = match ($data['type'] ?? 'string') {
                        'string' => $data['value_string'] ?? '',
                        'integer' => $data['value_integer'] ?? 0,
                        'boolean' => ($data['value_boolean'] ?? false) ? '1' : '0',
                        'json' => $data['value_json'] ?? '{}',
                        default => $data['value_string'] ?? '',
                    };

                    unset($data['value_string'], $data['value_integer'], $data['value_boolean'], $data['value_json']);

                    return $data;
                }),
        ];
    }
}
