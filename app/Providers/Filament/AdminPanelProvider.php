<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentGuestMode;
use Filament\Enums\UserMenuPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentColor;
use Filament\View\PanelsRenderHook;
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
        // Add JavaScript to reset navigation groups to collapsed state on fresh install
        // This runs once per session to ensure groups start collapsed
        Filament::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn () => new \Illuminate\Support\HtmlString($this->getNavigationResetScript())
        );

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
        // Check if system is properly configured (has plugins)
        // If not, redirect to public site instead of causing redirect loops
        if (! $this->hasRegisteredPlugins()) {
            $panel = $panel
                ->default()
                ->id('admin')
                ->path('admin')
                ->homeUrl('/');  // Redirect to public site when no plugins

            return $panel;
        }

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
            ->globalSearch(false)  // Disable global search (not configured)
            ->sidebarCollapsibleOnDesktop(true)  // Enable sidebar collapse (required for brand name to show)
            ->userMenu(position: UserMenuPosition::Sidebar)  // Force user menu to sidebar footer
            // Navigation groups are dynamically generated from enabled plugins
            // Each plugin maps 1:1 to a navigation group
            ->navigationGroups($this->buildDynamicNavigationGroups())
            // Resources are registered via Plugin system (see registerEnabledPlugins)
            // Dashboard page and widgets are provided by CorePlugin
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
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
     *
     * Plugins are loaded dynamically from the installed_plugins table.
     * The PluginRegistry handles dependency resolution and ordering.
     */
    protected function registerEnabledPlugins(Panel $panel): void
    {
        try {
            // During testing, register test plugins directly
            if (app()->environment('testing')) {
                $this->registerTestPlugins($panel);

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
                Log::warning('No plugins found in database - check installed_plugins table');
            }

        } catch (\Exception $e) {
            Log::error('Failed to register plugins: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
        }
    }

    /**
     * Register plugins for testing environment (no database dependency)
     */
    protected function registerTestPlugins(Panel $panel): void
    {
        // Minimal plugins needed for testing - Core and CMS
        $testPlugins = [
            \NetServa\Core\CorePlugin::class,
            \NetServa\Cms\NetServaCmsPlugin::class,
        ];

        foreach ($testPlugins as $pluginClass) {
            if (class_exists($pluginClass)) {
                try {
                    if (method_exists($pluginClass, 'make')) {
                        $panel->plugin($pluginClass::make());
                    } else {
                        $panel->plugin(new $pluginClass);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to register test plugin {$pluginClass}: ".$e->getMessage());
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
     * Check if the system has registered plugins
     *
     * If the table is empty, triggers auto-discovery via PluginRegistry
     * to handle fresh installations gracefully.
     */
    protected function hasRegisteredPlugins(): bool
    {
        try {
            // Skip check during testing
            if (app()->environment('testing')) {
                return true;
            }

            // Check if table exists
            if (! $this->tableExists('installed_plugins')) {
                return false;
            }

            // Check if any active plugins exist (uses model scope)
            if (InstalledPlugin::enabled()->exists()) {
                return true;
            }

            // Table exists but is empty - trigger auto-discovery
            // This handles fresh migrations where plugins need to be discovered
            $registry = app(PluginRegistry::class);
            $registry->getEnabledPluginsInOrder(); // Triggers autoDiscoverIfEmpty()

            // Check again after auto-discovery
            return InstalledPlugin::enabled()->exists();
        } catch (\Exception $e) {
            Log::warning('Failed to check for registered plugins: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Build navigation groups dynamically from enabled plugins
     *
     * Each enabled plugin creates one navigation group.
     * Groups are ordered by navigation_sort, then by name.
     *
     * IMPORTANT: Navigation group labels MUST match the $navigationGroup
     * property in resource classes (case-sensitive).
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
            // Note: Core IS included - it has Settings, Plugins, and AuditLog resources
            $plugins = InstalledPlugin::enabled()
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
     * After plugin consolidation, the 6 active packages are:
     * Core, Fleet, DNS, Mail, Web, CMS
     * Each plugin maps 1:1 to a navigation group.
     *
     * @return array<NavigationGroup>
     */
    protected function getDefaultNavigationGroups(): array
    {
        return [
            NavigationGroup::make()->label('Core')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            NavigationGroup::make()->label('Fleet')->icon('heroicon-o-rocket-launch')->collapsed(),
            NavigationGroup::make()->label('Dns')->icon('heroicon-o-globe-alt')->collapsed(),
            NavigationGroup::make()->label('Mail')->icon('heroicon-o-envelope')->collapsed(),
            NavigationGroup::make()->label('Web')->icon('heroicon-o-server')->collapsed(),
            NavigationGroup::make()->label('Cms')->icon('heroicon-o-document-text')->collapsed(),
        ];
    }

    /**
     * Get JavaScript to reset navigation groups to collapsed state
     *
     * Checks for a version marker in localStorage. When the app version changes
     * (e.g., after migrate:fresh), the navigation state is reset to collapsed.
     *
     * To force a reset: increment the nav_version in config/app.php or
     * change the marker format below.
     */
    protected function getNavigationResetScript(): string
    {
        // Use app name + nav version to create unique marker
        // Increment nav_version in config/app.php to force reset after migrate:fresh
        $navVersion = config('app.nav_version', 1);
        $versionMarker = config('app.name')."_nav_v{$navVersion}";

        return <<<HTML
<script>
(function() {
    const marker = '{$versionMarker}';
    const stored = localStorage.getItem('filament_nav_version');

    if (stored !== marker) {
        // Clear all Filament navigation group states
        Object.keys(localStorage).forEach(key => {
            if (key.includes('isOpen') || key.includes('collapsed') || key.includes('sidebar')) {
                localStorage.removeItem(key);
            }
        });
        // Set the version marker
        localStorage.setItem('filament_nav_version', marker);
    }
})();
</script>
HTML;
    }
}
