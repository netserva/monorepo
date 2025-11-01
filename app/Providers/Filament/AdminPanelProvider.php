<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentGuestMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use NetServa\Core\Foundation\PluginRegistry;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->topbar(false)  // Filament 4.1 feature: enables sticky navigation
            ->colors([
                'primary' => Color::Amber,
            ])
            // Navigation groups are now defined in Resource classes (Filament 4.x pattern)
            // Resources are registered via Plugin system (see registerEnabledPlugins)
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\InfrastructureOverview::class,
                \App\Filament\Widgets\ServiceHealthStatus::class,
                \App\Filament\Widgets\FleetHierarchy::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                FilamentGuestMode::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware(
                config('filament.auth_enabled', true) ? [Authenticate::class] : []
            );

        // Register all enabled plugins using native Filament plugin system
        $this->registerEnabledPlugins($panel);

        return $panel;
    }

    /**
     * Register all enabled plugins using native Filament plugin system
     */
    protected function registerEnabledPlugins(Panel $panel): void
    {
        try {
            // During testing, always register critical plugins first
            if (app()->environment('testing')) {
                $this->registerCriticalPlugins($panel);

                // In testing, ALWAYS return after critical plugins to skip database dependency
                return;
            }

            // Get plugin registry
            $registry = app(PluginRegistry::class);

            // Load plugins in dependency order (returns plugin classes)
            $enabledPluginClasses = $registry->getEnabledPluginsInOrder();

            $plugins = [];
            foreach ($enabledPluginClasses as $pluginClass) {
                if (class_exists($pluginClass)) {
                    try {
                        if (method_exists($pluginClass, 'make')) {
                            $plugins[] = $pluginClass::make();
                        } else {
                            $plugins[] = new $pluginClass;
                        }

                        $pluginId = (new $pluginClass)->getId();
                        Log::info("Registered plugin: {$pluginId}");
                    } catch (\Exception $e) {
                        Log::warning("Failed to register plugin {$pluginClass}: ".$e->getMessage());
                    }
                }
            }

            // Register all plugins at once
            if (! empty($plugins)) {
                $panel->plugins($plugins);
                Log::info('Registered '.count($plugins).' plugins successfully');
            }

        } catch (\Exception $e) {
            Log::error('Failed to register plugins: '.$e->getMessage());

            // TODO: Re-enable when plugin system is working
            // $this->registerCriticalPlugins($panel);
        }
    }

    /**
     * Fallback method to register critical plugins manually if registry fails
     */
    protected function registerCriticalPlugins(Panel $panel): void
    {
        $criticalPlugins = [
            \Ns\System\SystemPlugin::class,
            \Ns\Plugins\PluginsPlugin::class,
            \Ns\Ssh\SshPlugin::class,
            \Ns\Dns\DnsPlugin::class,
            \Ns\Example\ExamplePlugin::class,
            \Ns\Config\ConfigPlugin::class,
            \Ns\Database\DatabasePlugin::class,
            \Ns\Migration\MigrationPlugin::class,
            \Ns\Monitor\MonitorPlugin::class,
            \NetServa\Cms\NetServaCmsPlugin::class,
        ];

        foreach ($criticalPlugins as $pluginClass) {
            if (class_exists($pluginClass)) {
                try {
                    if (method_exists($pluginClass, 'make')) {
                        $panel->plugin($pluginClass::make());
                    } else {
                        $panel->plugin(new $pluginClass);
                    }
                    Log::info("Manually registered critical plugin: {$pluginClass}");
                } catch (\Exception $e) {
                    Log::warning("Failed to manually register plugin {$pluginClass}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Check if a table exists in the database
     */
    protected function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }
}
