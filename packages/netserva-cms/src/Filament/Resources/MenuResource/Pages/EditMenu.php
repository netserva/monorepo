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
                ->modalFooterActionsAlignment(Alignment::End)
                ->infolist([
                    TextEntry::make('preview')
                        ->hiddenLabel()
                        ->html()
                        ->getStateUsing(fn () => $this->renderMenuPreview($this->form->getState()['items'] ?? [])),
                ]),

            Actions\DeleteAction::make()
                ->iconButton()
                ->tooltip('Delete menu'),
        ];
    }

    /**
     * Render a visual preview of the menu structure
     */
    protected function renderMenuPreview(?array $items = null): string
    {
        $items = $items ?? $this->record->items ?? [];

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
    protected function renderMenuItem(array $item, int $depth = 0): string
    {
        $label = e($item['label'] ?? 'Untitled');
        $newWindow = $item['new_window'] ?? false;
        $children = $item['children'] ?? [];

        $paddingLeft = ($depth * 24).'px';
        $textSize = $depth > 0 ? 'text-sm' : 'text-base';
        $fontWeight = $depth > 0 ? 'font-normal' : 'font-medium';
        $bullet = $depth > 0 ? '└ ' : '';

        $externalIcon = $newWindow ? ' <span class="text-gray-400 text-xs">↗</span>' : '';

        $html = '<div class="py-1.5 border-b border-gray-100 dark:border-gray-700" style="padding-left: '.$paddingLeft.'">';
        $html .= '<span class="'.$textSize.' '.$fontWeight.' text-gray-900 dark:text-gray-100">'.$bullet.$label.'</span>';
        $html .= $externalIcon;
        $html .= '</div>';

        // Render children after parent (not nested inside)
        if (! empty($children)) {
            foreach ($children as $child) {
                $html .= $this->renderMenuItem($child, $depth + 1);
            }
        }

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
