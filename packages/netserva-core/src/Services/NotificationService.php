<?php

namespace NetServa\Core\Services;

use Filament\Notifications\Notification;

/**
 * Notification Service
 *
 * Provides centralized notification management for the NetServa ecosystem.
 */
class NotificationService
{
    /**
     * Send a success notification
     */
    public function success(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * Send an info notification
     */
    public function info(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->info()
            ->send();
    }

    /**
     * Send a warning notification
     */
    public function warning(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->send();
    }

    /**
     * Send an error notification
     */
    public function error(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();
    }

    /**
     * Send a custom notification
     */
    public function notify(string $title, ?string $body = null, string $type = 'info', array $actions = []): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($body);

        match ($type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'error', 'danger' => $notification->danger(),
            default => $notification->info(),
        };

        foreach ($actions as $action) {
            $notification->actions([$action]);
        }

        $notification->send();
    }

    /**
     * Send a persistent notification that requires dismissal
     */
    public function persistent(string $title, ?string $body = null, string $type = 'info'): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->persistent();

        match ($type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'error', 'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();
    }

    /**
     * Send notification for plugin events
     */
    public function plugin(string $pluginId, string $action, string $type = 'info'): void
    {
        $title = match ($action) {
            'enabled' => "Plugin {$pluginId} enabled",
            'disabled' => "Plugin {$pluginId} disabled",
            'installed' => "Plugin {$pluginId} installed",
            'uninstalled' => "Plugin {$pluginId} uninstalled",
            'updated' => "Plugin {$pluginId} updated",
            default => "Plugin {$pluginId}: {$action}",
        };

        $this->notify($title, null, $type);
    }
}
