<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PostResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Cms\Filament\Resources\PostResource;
use NetServa\Cms\Filament\Resources\PostResource\Schemas\PostFormSchemas;
use Override;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

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
                ->tooltip('Back to posts')
                ->iconButton()
                ->url($this->getResource()::getUrl('index')),

            ActionGroup::make([
                Action::make('basic')
                    ->icon('heroicon-o-document-text')
                    ->tooltip('Title, slug, author, excerpt')
                    ->modalHeading('Basic Information')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PostFormSchemas::getBasicSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('seo')
                    ->icon('heroicon-o-magnifying-glass')
                    ->tooltip('Meta title, description, social sharing')
                    ->modalHeading('SEO & Social')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PostFormSchemas::getSeoSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('categories')
                    ->icon('heroicon-o-tag')
                    ->tooltip('Categories and tags')
                    ->modalHeading('Categories & Tags')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PostFormSchemas::getCategoriesSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('publish')
                    ->icon('heroicon-o-calendar')
                    ->tooltip('Publish status and date')
                    ->modalHeading('Publishing')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PostFormSchemas::getPublishSchema())
                    ->action(fn (array $data) => $this->modalData = array_merge($this->modalData, $data)),

                Action::make('media')
                    ->icon('heroicon-o-photo')
                    ->tooltip('Featured image and gallery')
                    ->modalHeading('Media')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->modalData)
                    ->schema(PostFormSchemas::getMediaSchema())
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
                ->tooltip('Save post first to preview'),
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
