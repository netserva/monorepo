<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\PostResource\Pages;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Cms\Filament\Resources\PostResource;
use NetServa\Cms\Filament\Resources\PostResource\Schemas\PostFormSchemas;
use Override;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

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
                    ->fillForm(fn (): array => $this->record->only(['title', 'slug', 'author_id', 'excerpt']))
                    ->schema(PostFormSchemas::getBasicSchema())
                    ->action(function (array $data) {
                        $this->record->update($data);
                        Notification::make()->title('Saved')->success()->send();
                    }),

                Action::make('seo')
                    ->icon('heroicon-o-magnifying-glass')
                    ->tooltip('Meta title, description, social sharing')
                    ->modalHeading('SEO & Social')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->record->only(['meta_title', 'meta_description', 'meta_keywords', 'og_image', 'twitter_card']))
                    ->schema(PostFormSchemas::getSeoSchema())
                    ->action(function (array $data) {
                        $this->record->update($data);
                        Notification::make()->title('Saved')->success()->send();
                    }),

                Action::make('categories')
                    ->icon('heroicon-o-tag')
                    ->tooltip('Categories and tags')
                    ->modalHeading('Categories & Tags')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => [
                        'categories' => $this->record->categories->pluck('id')->toArray(),
                        'tags' => $this->record->tags->pluck('id')->toArray(),
                    ])
                    ->schema(PostFormSchemas::getCategoriesSchema())
                    ->action(function (array $data) {
                        $this->record->categories()->sync($data['categories'] ?? []);
                        $this->record->tags()->sync($data['tags'] ?? []);
                        Notification::make()->title('Saved')->success()->send();
                    }),

                Action::make('publish')
                    ->icon('heroicon-o-calendar')
                    ->tooltip('Publish status and date')
                    ->modalHeading('Publishing')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->record->only(['is_published', 'published_at']))
                    ->schema(PostFormSchemas::getPublishSchema())
                    ->action(function (array $data) {
                        $this->record->update($data);
                        Notification::make()->title('Saved')->success()->send();
                    }),

                Action::make('media')
                    ->icon('heroicon-o-photo')
                    ->tooltip('Featured image and gallery')
                    ->modalHeading('Media')
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (): array => $this->record->only(['featured_image', 'gallery']))
                    ->schema(PostFormSchemas::getMediaSchema())
                    ->action(function (array $data) {
                        $this->record->update($data);
                        Notification::make()->title('Saved')->success()->send();
                    }),
            ])
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),

            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn () => $this->record->title)
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->infolist([
                    \Filament\Infolists\Components\TextEntry::make('content')
                        ->hiddenLabel()
                        ->html()
                        ->prose()
                        ->getStateUsing(fn () => $this->record->content),
                ]),

            Actions\DeleteAction::make()
                ->iconButton()
                ->tooltip('Delete post'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    #[Override]
    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
