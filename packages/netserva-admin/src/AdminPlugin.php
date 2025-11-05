<?php

declare(strict_types=1);

namespace NetServa\Admin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use NetServa\Admin\Filament\Resources\AuditLogResource;
use NetServa\Admin\Filament\Resources\PluginResource;
use NetServa\Admin\Filament\Resources\SettingResource;

class AdminPlugin implements Plugin
{
    public function getId(): string
    {
        return 'netserva-admin';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('netserva-admin.resources.settings', true)) {
            $resources[] = SettingResource::class;
        }

        if (config('netserva-admin.resources.plugins', true)) {
            $resources[] = PluginResource::class;
        }

        if (config('netserva-admin.resources.audit_logs', true)) {
            $resources[] = AuditLogResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
