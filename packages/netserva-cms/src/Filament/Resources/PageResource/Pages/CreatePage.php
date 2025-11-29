<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PageResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Cms\Filament\Resources\PageResource;
use NetServa\Cms\Filament\Resources\PageResource\Schemas\PageFormSchemas;
use Override;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

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
                ->tooltip('Back to pages')
                ->iconButton()
                ->url($this->getResource()::getUrl('index')),

            ActionGroup::make([
                Action::make('basic')
                    ->icon('heroicon-o-document-text')
                    ->tooltip('Title, slug, template, excerpt')
                    ->modalHeading('Basic Information')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PageFormSchemas::getBasicSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('seo')
                    ->icon('heroicon-o-magnifying-glass')
                    ->tooltip('Meta title, description, social sharing')
                    ->modalHeading('SEO & Social')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PageFormSchemas::getSeoSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('hierarchy')
                    ->icon('heroicon-o-folder-open')
                    ->tooltip('Parent page and order')
                    ->modalHeading('Hierarchy')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PageFormSchemas::getHierarchySchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('publish')
                    ->icon('heroicon-o-calendar')
                    ->tooltip('Publish status and date')
                    ->modalHeading('Publishing')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PageFormSchemas::getPublishSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('media')
                    ->icon('heroicon-o-photo')
                    ->tooltip('Featured image and gallery')
                    ->modalHeading('Media')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PageFormSchemas::getMediaSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),
            ])
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),

            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->disabled()
                ->tooltip('Save page first to preview'),
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
