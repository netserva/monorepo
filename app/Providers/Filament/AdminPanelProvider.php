<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentGuestMode;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Models\InstalledPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Register dynamic colors using a closure that executes AFTER authentication
        // This is the official Filament v4 way to handle user-specific colors
        FilamentColor::register(function () {
            try {
                // Check if user is authenticated and has a palette
                if (! auth()->check() || ! auth()->user()->palette_id) {
                    return \App\Models\Palette::default()->getFilamentColors();
                }

                // Get authenticated user's palette
                $palette = auth()->user()->palette;

                if (! $palette) {
                    return \App\Models\Palette::default()->getFilamentColors();
                }

                return $palette->getFilamentColors();
            } catch (\Exception $e) {
                Log::error('Failed to load user palette colors: '.$e->getMessage());

                return \App\Models\Palette::default()->getFilamentColors();
            }
        });
    }

    public function panel(Panel $panel): Panel
    {
        // Colors are registered via FilamentColor::register() in boot() method
        // No need to set them here - the closure executes after auth middleware
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            ->brandName(fn () => config('app.name'))  // Dynamic brand from config (overridden by CMS if available)
            ->topbar(false)  // Disable topbar entirely (Filament v4 method)
            ->sidebarCollapsibleOnDesktop(true)  // Enable sidebar collapse (required for brand name to show)
            ->userMenu(position: UserMenuPosition::Sidebar)  // Force user menu to sidebar footer
            // Navigation groups are dynamically generated from enabled plugins
            // Each plugin maps 1:1 to a navigation group
            ->navigationGroups($this->buildDynamicNavigationGroups())
            // Resources are registered via Plugin system (see registerEnabledPlugins)
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                // Temporarily disabled all widgets until we fix route issues
                // \App\Filament\Widgets\InfrastructureOverview::class,
                // \App\Filament\Widgets\ServiceHealthStatus::class,
                // \App\Filament\Widgets\FleetHierarchy::class,
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
                    } catch (\Exception $e) {
                        Log::warning("Failed to register plugin {$pluginClass}: ".$e->getMessage());
                    }
                }
            }

            // Register all plugins at once
            if (! empty($plugins)) {
                $panel->plugins($plugins);
            } else {
                Log::warning('No plugins to register, falling back to critical plugins');
                $this->registerCriticalPlugins($panel);
            }

        } catch (\Exception $e) {
            Log::error('Failed to register plugins: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            // Fallback to manual registration of critical plugins
            $this->registerCriticalPlugins($panel);
        }
    }

    /**
     * Fallback method to register critical plugins manually if registry fails
     */
    protected function registerCriticalPlugins(Panel $panel): void
    {
        $criticalPlugins = [
            // Admin plugin provides Settings, Plugins, and Audit Log resources
            \NetServa\Admin\AdminPlugin::class,

            // CMS plugin
            \NetServa\Cms\NetServaCmsPlugin::class,

            // Temporarily disabled Fleet plugin until routes are fixed
            // \NetServa\Fleet\Filament\FleetPlugin::class,
            // \NetServa\Dns\Filament\NetServaDnsPlugin::class,
            // \NetServa\Config\Filament\NetServaConfigPlugin::class,
            // \NetServa\Mail\Filament\NetServaMailPlugin::class,
            // \NetServa\Web\Filament\NetServaWebPlugin::class,
            // \NetServa\Ops\Filament\NetServaOpsPlugin::class,
            // \NetServa\Cli\Filament\NetServaCliPlugin::class,
            // \NetServa\Cron\Filament\NetServaCronPlugin::class,
            // \NetServa\Ipam\Filament\NetServaIpamPlugin::class,
            // \NetServa\Wg\Filament\NetServaWgPlugin::class,
        ];

        foreach ($criticalPlugins as $pluginClass) {
            if (class_exists($pluginClass)) {
                try {
                    if (method_exists($pluginClass, 'make')) {
                        $panel->plugin($pluginClass::make());
                    } else {
                        $panel->plugin(new $pluginClass);
                    }
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

    /**
     * Build navigation groups dynamically from enabled plugins
     *
     * Each enabled plugin creates one navigation group.
     * Groups are ordered by navigation_sort, then by name.
     * Core plugin is excluded (no UI navigation).
     *
     * @return array<NavigationGroup>
     */
    protected function buildDynamicNavigationGroups(): array
    {
        try {
            // Skip database lookup during testing
            if (app()->environment('testing')) {
                return $this->getDefaultNavigationGroups();
            }

            // Check if table exists
            if (! $this->tableExists('installed_plugins')) {
                return $this->getDefaultNavigationGroups();
            }

            // Get enabled plugins ordered by navigation_sort
            $plugins = InstalledPlugin::enabled()
                ->where('name', '!=', 'netserva-core') // Core has no UI
                ->navigationOrder()
                ->get();

            if ($plugins->isEmpty()) {
                return $this->getDefaultNavigationGroups();
            }

            $groups = [];
            foreach ($plugins as $plugin) {
                $groups[] = NavigationGroup::make()
                    ->label($plugin->getNavigationGroupName())
                    ->icon($plugin->getNavigationIcon())
                    ->collapsed();
            }

            return $groups;

        } catch (\Exception $e) {
            Log::warning('Failed to build dynamic navigation groups: '.$e->getMessage());

            return $this->getDefaultNavigationGroups();
        }
    }

    /**
     * Fallback navigation groups when database is unavailable
     *
     * @return array<NavigationGroup>
     */
    protected function getDefaultNavigationGroups(): array
    {
        return [
            NavigationGroup::make()->label('Core')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            NavigationGroup::make()->label('Fleet')->icon('heroicon-o-rocket-launch')->collapsed(),
            NavigationGroup::make()->label('Cms')->icon('heroicon-o-document-text')->collapsed(),
            NavigationGroup::make()->label('Dns')->icon('heroicon-o-globe-alt')->collapsed(),
            NavigationGroup::make()->label('Mail')->icon('heroicon-o-envelope')->collapsed(),
            NavigationGroup::make()->label('Web')->icon('heroicon-o-server')->collapsed(),
            NavigationGroup::make()->label('Config')->icon('heroicon-o-wrench-screwdriver')->collapsed(),
            NavigationGroup::make()->label('Ipam')->icon('heroicon-o-computer-desktop')->collapsed(),
            NavigationGroup::make()->label('Wg')->icon('heroicon-o-shield-check')->collapsed(),
            NavigationGroup::make()->label('Cli')->icon('heroicon-o-command-line')->collapsed(),
            NavigationGroup::make()->label('Ops')->icon('heroicon-o-chart-bar-square')->collapsed(),
            // Cron merged into Ops - removed from navigation groups
        ];
    }
}
