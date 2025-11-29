<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\MenuResource\Pages;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Cms\Filament\Resources\MenuResource;
use NetServa\Cms\Filament\Resources\MenuResource\Schemas\MenuFormSchemas;
use Override;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

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
                ->fillForm(fn (): array => $this->record->only(['name', 'location', 'is_active']))
                ->schema(MenuFormSchemas::getDetailsSchema())
                ->action(function (array $data) {
                    $this->record->update($data);
                    Notification::make()->title('Saved')->success()->send();
                }),

            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn () => $this->record->name.' ('.$this->record->location.')')
                ->modalWidth(Width::Medium)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->infolist([
                    TextEntry::make('preview')
                        ->hiddenLabel()
                        ->html()
                        ->getStateUsing(fn () => $this->renderMenuPreview()),
                ]),

            Actions\DeleteAction::make()
                ->iconButton()
                ->tooltip('Delete menu'),
        ];
    }

    /**
     * Render a visual preview of the menu structure
     */
    protected function renderMenuPreview(): string
    {
        $items = $this->record->items ?? [];

        if (empty($items)) {
            return '<p class="text-gray-500 italic">No menu items defined</p>';
        }

        $html = '<nav class="space-y-1">';

        foreach ($items as $item) {
            $html .= $this->renderMenuItem($item);
        }

        $html .= '</nav>';

        return $html;
    }

    /**
     * Render a single menu item with its children
     */
    protected function renderMenuItem(array $item, bool $isChild = false): string
    {
        $label = e($item['label'] ?? 'Untitled');
        $url = e($item['url'] ?? '#');
        $icon = $item['icon'] ?? null;
        $newWindow = $item['new_window'] ?? false;
        $children = $item['children'] ?? [];

        $padding = $isChild ? 'pl-6' : '';
        $textSize = $isChild ? 'text-sm' : 'text-base';
        $fontWeight = $isChild ? 'font-normal' : 'font-medium';

        $iconHtml = '';
        if ($icon) {
            $iconHtml = '<span class="text-gray-400 mr-2 text-xs">['.$icon.']</span>';
        }

        $externalIcon = $newWindow ? ' <span class="text-gray-400 text-xs">↗</span>' : '';

        $html = '<div class="'.$padding.' py-2 border-b border-gray-100 dark:border-gray-700">';
        $html .= '<div class="flex items-center">';
        $html .= $iconHtml;
        $html .= '<span class="'.$textSize.' '.$fontWeight.' text-gray-900 dark:text-gray-100">'.$label.'</span>';
        $html .= $externalIcon;
        $html .= '<span class="ml-auto text-xs text-gray-400">'.$url.'</span>';
        $html .= '</div>';

        // Render children
        if (! empty($children)) {
            foreach ($children as $child) {
                $html .= $this->renderMenuItem($child, true);
            }
        }

        $html .= '</div>';

        return $html;
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
