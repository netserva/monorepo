<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\MenuResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Cms\Filament\Resources\MenuResource;
use NetServa\Cms\Filament\Resources\MenuResource\Schemas\MenuFormSchemas;
use Override;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    // Disable "Create & create another" button
    protected static bool $canCreateAnother = false;

    // Store modal data temporarily until form submission
    public array $modalData = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->tooltip('Back to menus')
                ->iconButton()
                ->url($this->getResource()::getUrl('index')),

            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->tooltip('Menu name, location, active status')
                ->modalHeading('Menu Settings')
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->fillForm(fn (): array => $this->modalData)
                ->schema(MenuFormSchemas::getDetailsSchema())
                ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    #[Override]
    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Merge modal data with form data before saving
        return array_merge($data, $this->modalData);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
