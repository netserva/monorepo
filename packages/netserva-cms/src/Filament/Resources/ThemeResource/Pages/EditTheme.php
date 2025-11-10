<?php

declare(strict_types=1);

namespace NetServa\Cms\Filament\Resources\ThemeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Cms\Filament\Resources\ThemeResource;
use NetServa\Cms\Models\Theme;
use NetServa\Cms\Services\ThemeService;

/**
 * Edit Theme Page
 */
class EditTheme extends EditRecord
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Theme')
                ->modalDescription(fn () => "Are you sure you want to activate '{$this->record->display_name}'? This will deactivate the current theme.")
                ->hidden(fn () => $this->record->is_active)
                ->action(function () {
                    $service = app(ThemeService::class);
                    $service->activate($this->record->name);

                    \Filament\Notifications\Notification::make()
                        ->title('Theme Activated')
                        ->body("'{$this->record->display_name}' is now the active theme.")
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            \Filament\Actions\Action::make('refresh_manifest')
                ->label('Reload Manifest')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Reload Theme Manifest')
                ->modalDescription('This will reload the theme.json file from the filesystem and update the database.')
                ->action(function () {
                    $manifest = Theme::loadManifest($this->record->name);

                    if ($manifest) {
                        $this->record->update([
                            'manifest' => $manifest,
                            'display_name' => $manifest['display_name'] ?? $this->record->display_name,
                            'description' => $manifest['description'] ?? $this->record->description,
                            'version' => $manifest['version'] ?? $this->record->version,
                            'author' => $manifest['author'] ?? $this->record->author,
                            'parent_theme' => $manifest['parent'] ?? $this->record->parent_theme,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Manifest Reloaded')
                            ->body('Theme manifest has been updated from filesystem.')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Manifest Not Found')
                            ->body('Could not find theme.json file for this theme.')
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make()
                ->hidden(fn () => $this->record->is_active)
                ->requiresConfirmation()
                ->modalDescription('Are you sure? This will remove the theme from the database (files remain).'),
        ];
    }

    protected function afterSave(): void
    {
        // Clear theme cache when settings are updated
        if ($this->record->is_active) {
            app(ThemeService::class)->clearCache();
        }
    }
}
