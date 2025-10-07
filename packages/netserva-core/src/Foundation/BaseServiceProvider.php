<?php

namespace NetServa\Core\Foundation;

use Illuminate\Support\ServiceProvider;
use NetServa\Core\Contracts\PluginInterface;

abstract class BaseServiceProvider extends ServiceProvider
{
    /**
     * The plugin instance
     */
    protected ?PluginInterface $plugin = null;

    /**
     * The plugin's package name (e.g., 'ns-ssh-manager')
     */
    protected string $packageName;

    /**
     * The plugin's namespace (e.g., 'Ns\\')
     */
    protected string $packageNamespace;

    /**
     * Console commands to register
     */
    protected array $commands = [];

    /**
     * Services to register as singletons
     * Note: Must be public for Laravel's Application class to access
     */
    public array $singletons = [];

    /**
     * Services to register
     */
    protected array $services = [];

    /**
     * Register any application services
     */
    public function register(): void
    {
        // Register the plugin instance if it exists
        if ($this->plugin) {
            $pluginId = $this->plugin->getId();
            $this->app->singleton("ns.plugin.{$pluginId}", fn () => $this->plugin);
        }

        // Register singletons
        foreach ($this->singletons as $abstract => $concrete) {
            if (is_numeric($abstract)) {
                $this->app->singleton($concrete);
            } else {
                $this->app->singleton($abstract, $concrete);
            }
        }

        // Register services
        foreach ($this->services as $abstract => $concrete) {
            if (is_numeric($abstract)) {
                $this->app->bind($concrete);
            } else {
                $this->app->bind($abstract, $concrete);
            }
        }

        // Register config if exists
        $this->registerConfig();
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrations();

        // Load routes
        $this->loadRoutes();

        // Load views
        $this->loadViews();

        // Load translations
        $this->loadTranslations();

        // Register commands
        $this->registerCommands();

        // Register model factories
        $this->registerFactories();

        // Publish assets
        $this->publishAssets();

        // Boot the plugin
        if ($this->plugin) {
            $this->plugin->boot();
        }
    }

    /**
     * Load migrations from the package
     */
    protected function loadMigrations(): void
    {
        $migrationsPath = $this->getPackagePath('database/migrations');
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Load routes from the package
     */
    protected function loadRoutes(): void
    {
        $webRoutesPath = $this->getPackagePath('routes/web.php');
        if (file_exists($webRoutesPath)) {
            $this->loadRoutesFrom($webRoutesPath);
        }

        $apiRoutesPath = $this->getPackagePath('routes/api.php');
        if (file_exists($apiRoutesPath)) {
            $this->loadRoutesFrom($apiRoutesPath);
        }
    }

    /**
     * Load views from the package
     */
    protected function loadViews(): void
    {
        $viewsPath = $this->getPackagePath('resources/views');
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->packageName);
        }
    }

    /**
     * Load translations from the package
     */
    protected function loadTranslations(): void
    {
        $langPath = $this->getPackagePath('resources/lang');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->packageName);
        }
    }

    /**
     * Register console commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            // Add plugin commands if available
            if ($this->plugin) {
                $this->commands = array_merge($this->commands, $this->plugin->getCommands());
            }

            if (! empty($this->commands)) {
                $this->commands($this->commands);
            }
        }
    }

    /**
     * Register model factories
     */
    protected function registerFactories(): void
    {
        $factoriesPath = $this->getPackagePath('database/factories');
        if (is_dir($factoriesPath)) {
            // Set up factory discovery for this package namespace
            $packageNamespace = $this->packageNamespace;

            \Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing(function (string $modelName) use ($packageNamespace) {
                // Only handle models from this package
                if (str_starts_with($modelName, $packageNamespace)) {
                    $modelBaseName = class_basename($modelName);

                    return $packageNamespace.'\\Database\\Factories\\'.$modelBaseName.'Factory';
                }

                // Return null to let other packages or Laravel handle their models
                return null;
            });
        }
    }

    /**
     * Register configuration
     */
    protected function registerConfig(): void
    {
        $configPath = $this->getPackagePath('config');
        if (is_dir($configPath)) {
            foreach (glob($configPath.'/*.php') as $configFile) {
                $configName = basename($configFile, '.php');
                $this->mergeConfigFrom($configFile, $configName);
            }
        }
    }

    /**
     * Publish package assets
     */
    protected function publishAssets(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Publish config files
        $configPath = $this->getPackagePath('config');
        if (is_dir($configPath)) {
            $this->publishes([
                $configPath => config_path(),
            ], "{$this->packageName}-config");
        }

        // Publish views
        $viewsPath = $this->getPackagePath('resources/views');
        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path("views/vendor/{$this->packageName}"),
            ], "{$this->packageName}-views");
        }

        // Publish migrations
        $migrationsPath = $this->getPackagePath('database/migrations');
        if (is_dir($migrationsPath)) {
            $this->publishes([
                $migrationsPath => database_path('migrations'),
            ], "{$this->packageName}-migrations");
        }

        // Publish assets
        $assetsPath = $this->getPackagePath('resources/dist');
        if (is_dir($assetsPath)) {
            $this->publishes([
                $assetsPath => public_path("vendor/{$this->packageName}"),
            ], "{$this->packageName}-assets");
        }
    }

    /**
     * Get the package base path
     */
    protected function getPackagePath(string $path = ''): string
    {
        $basePath = dirname((new \ReflectionClass($this))->getFileName()).'/../';

        return $path ? $basePath.$path : $basePath;
    }
}
