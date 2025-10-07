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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                '🏗️ Infrastructure',
                '🌐 Networking',
                '📧 Email & Messaging',
                '🔧 Configuration',
                '📊 Operations',
                '🚀 Fleet Management',
                '⚙️ System',
            ])
            // Auto-discover resources from all NetServa packages
            ->discoverResources(in: base_path('packages/netserva-cli/src/Filament/Resources'), for: 'NetServa\\Cli\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-config/src/Filament/Resources'), for: 'NetServa\\Config\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-core/src/Filament/Resources'), for: 'NetServa\\Core\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-cron/src/Filament/Resources'), for: 'NetServa\\Cron\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-dns/src/Filament/Resources'), for: 'NetServa\\Dns\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-fleet/src/Filament/Resources'), for: 'NetServa\\Fleet\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-ipam/src/Filament/Resources'), for: 'NetServa\\Ipam\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-mail/src/Filament/Resources'), for: 'NetServa\\Mail\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-ops/src/Filament/Resources'), for: 'NetServa\\Ops\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-web/src/Filament/Resources'), for: 'NetServa\\Web\\Filament\\Resources')
            ->discoverResources(in: base_path('packages/netserva-wg/src/Filament/Resources'), for: 'NetServa\\Wg\\Filament\\Resources')
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

            // Load plugins in dependency order
            $enabledPluginIds = $registry->getEnabledPluginsInOrder();

            $plugins = [];
            foreach ($enabledPluginIds as $pluginId) {
                $pluginClass = $registry->getPluginClass($pluginId);

                if ($pluginClass && class_exists($pluginClass)) {
                    try {
                        if (method_exists($pluginClass, 'make')) {
                            $plugins[] = $pluginClass::make();
                        } else {
                            $plugins[] = new $pluginClass;
                        }

                        Log::info("Registered plugin: {$pluginId}");
                    } catch (\Exception $e) {
                        Log::warning("Failed to register plugin {$pluginId}: ".$e->getMessage());
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
