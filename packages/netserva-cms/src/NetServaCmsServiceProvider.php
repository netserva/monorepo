<?php

declare(strict_types=1);

namespace NetServa\Cms;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use NetServa\Cms\Services\ThemeService;

/**
 * NetServa CMS Service Provider
 *
 * Standard Laravel service provider - NO NetServa dependencies
 * This package is designed to be deployed standalone
 */
class NetServaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/netserva-cms.php',
            'netserva-cms'
        );

        // Load helper functions
        require_once __DIR__.'/helpers.php';

        // Register ThemeService as singleton
        $this->app->singleton(ThemeService::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\ExportCommand::class,
                Console\Commands\ImportCommand::class,
                Console\Commands\ResetCommand::class,
            ]);
        }

        // Register as NetServa plugin if core is available
        $this->registerAsPluginIfCoreAvailable();
    }

    /**
     * Register CMS as a NetServa plugin if core package is available
     */
    protected function registerAsPluginIfCoreAvailable(): void
    {
        // Check if NetServa Core is installed
        if (! class_exists(\NetServa\Core\Services\PluginManager::class)) {
            return;
        }

        // Register CMS as a plugin for discovery
        try {
            $pluginManager = $this->app->make(\NetServa\Core\Services\PluginManager::class);

            // CMS metadata for plugin system
            $pluginManager->registerPlugin([
                'name' => 'NetServa CMS',
                'package_name' => 'netserva/cms',
                'plugin_class' => static::class,
                'version' => '3.0.0',
                'description' => 'Professional Laravel CMS with Filament 4 admin panel',
                'category' => 'content',
                'dependencies' => [],
            ]);
        } catch (\Exception $e) {
            // Silently fail - CMS works standalone
            // Core integration is optional enhancement only
        }
    }

    /**
     * Remove the default Laravel welcome route to allow CMS to handle root route
     *
     * This automatically removes Laravel's default welcome route if it exists,
     * allowing the CMS to seamlessly take over the root route without requiring
     * manual edits to routes/web.php.
     */
    protected function removeDefaultWelcomeRoute(): void
    {
        $router = $this->app->make('router');
        $routes = $router->getRoutes();

        // Find and remove any GET route to '/' (typically the Laravel welcome route)
        foreach ($routes->get('GET') as $route) {
            if ($route->uri() === '/') {
                // Check if it's likely the default Laravel welcome route
                // by checking the action (usually a view closure or named 'welcome')
                $action = $route->getAction();

                // Remove if it's a closure (default Laravel welcome) or named 'welcome'
                if (isset($action['uses']) && $action['uses'] instanceof \Closure) {
                    $routes->remove($route);

                    break;
                }

                // Also remove if explicitly named 'welcome'
                if (($route->getName() === 'welcome') || ($route->getName() === null && $route->uri() === '/')) {
                    $routes->remove($route);

                    break;
                }
            }
        }
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load settings migrations (populates CMS settings if Core is installed)
        $this->loadMigrationsFrom(__DIR__.'/../database/settings');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-cms');

        // Register Blade directives for themes
        $this->registerBladeDirectives();

        // Discover and register themes
        $this->discoverThemes();

        // Register active theme view paths
        $this->registerThemeViewPaths();

        // Automatically remove Laravel welcome route if CMS frontend is enabled
        if (config('netserva-cms.frontend.enabled', true)) {
            $this->removeDefaultWelcomeRoute();
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Publishable assets
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/netserva-cms.php' => config_path('netserva-cms.php'),
            ], 'netserva-cms-config');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/netserva-cms'),
            ], 'netserva-cms-views');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'netserva-cms-migrations');
        }
    }

    /**
     * Register Blade directives for theme system
     */
    protected function registerBladeDirectives(): void
    {
        // @theme('colors.primary') - Get theme setting value
        Blade::directive('theme', function ($expression) {
            return "<?php echo e(theme({$expression})); ?>";
        });

        // @themeAsset('images/logo.svg') - Get theme asset URL
        Blade::directive('themeAsset', function ($expression) {
            return "<?php echo e(theme_asset({$expression})); ?>";
        });
    }

    /**
     * Discover and register themes from filesystem
     */
    protected function discoverThemes(): void
    {
        try {
            $themeService = $this->app->make(ThemeService::class);
            $themeService->discover();
        } catch (\Exception $e) {
            // Silently fail during boot - themes may not be migrated yet
        }
    }

    /**
     * Register active theme view paths
     */
    protected function registerThemeViewPaths(): void
    {
        try {
            $themeService = $this->app->make(ThemeService::class);
            $activeTheme = $themeService->getActive();
            $themeService->registerViewPaths($activeTheme);
        } catch (\Exception $e) {
            // Silently fail during boot - themes may not be migrated yet
        }
    }
}
