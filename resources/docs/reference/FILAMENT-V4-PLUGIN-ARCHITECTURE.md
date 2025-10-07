# Filament v4 Plugin Architecture - NetServa Platform

## 🎯 Overview

This document describes the NetServa Platform's migration from a custom plugin system to Filament v4's native plugin architecture, achieving maximum leverage of Filament's plugin capabilities while maintaining runtime enable/disable functionality and dependency management.

## 🏗️ Architecture Transformation

### Before: Custom NsPluginInterface System
```php
interface NsPluginInterface {
    public function getName(): string;
    public function getDescription(): string;
    public function isEnabled(): bool;
    // Custom registration methods
}
```

### After: Native Filament Plugin Interface
```php
use Filament\Contracts\Plugin;

class ExamplePlugin extends BaseFilamentPlugin implements Plugin {
    public function getId(): string;
    public function register(Panel $panel): void;
    public function boot(Panel $panel): void;
}
```

## 🚀 Key Benefits Achieved

### 1. Native Filament Integration
- **Auto-discovery**: Plugins automatically register resources, pages, and widgets
- **Panel Integration**: Seamless integration with Filament's panel system
- **Asset Management**: Built-in support for CSS/JS assets with lazy loading
- **Lifecycle Hooks**: Proper register → boot phases for dependency injection

### 2. Enhanced Plugin Management
- **Dependency Resolution**: Topological sorting ensures correct plugin load order
- **Runtime Control**: Enable/disable plugins without code changes
- **Database Persistence**: Plugin states stored in `installed_plugins` table
- **Circular Dependency Detection**: Prevents infinite loops in plugin dependencies

### 3. Developer Experience
- **Type Safety**: Full PHP 8.4+ type declarations with union types
- **Base Plugin Class**: `BaseFilamentPlugin` provides common functionality
- **Plugin Discovery**: Automatic detection of plugins implementing Plugin interface
- **Comprehensive UI**: Management interface for plugin control and monitoring

## 📋 Core Components

### 1. BaseFilamentPlugin (Abstract Base Class)
**Location**: `packages/ns-plugins/src/BaseFilamentPlugin.php`

```php
abstract class BaseFilamentPlugin implements Plugin
{
    // Core Plugin Interface Methods
    abstract public function getId(): string;

    public function register(Panel $panel): void {
        if (!$this->isEnabled()) return;
        if (!$this->checkDependencies($panel)) return;

        $this->registerResources($panel);
        $this->registerPages($panel);
        $this->registerWidgets($panel);
        $this->registerNavigationItems($panel);
        $this->registerAssets($panel);
    }

    public function boot(Panel $panel): void {
        if (!$this->isEnabled()) return;

        $this->bootServices();
        $this->registerRoutes();
        $this->publishAssets();
    }

    // Enhanced Features
    public function isEnabled(): bool;
    public function checkDependencies(Panel $panel): bool;
    public function getDependencies(): array;
    protected function registerResources(Panel $panel): void;
    protected function registerPages(Panel $panel): void;
    protected function registerWidgets(Panel $panel): void;
    // ... additional hook methods
}
```

### 2. PluginRegistry (Discovery & Management)
**Location**: `packages/ns-plugins/src/PluginRegistry.php`

```php
class PluginRegistry
{
    protected array $availablePlugins = [];
    protected DependencyResolver $dependencyResolver;

    // Core Registry Functions
    public function getAvailablePluginIds(): array;
    public function getEnabledPluginsInOrder(): array; // With dependency resolution
    public function installPlugin(string $pluginId, bool $enable = true): bool;
    public function enablePlugin(string $pluginId): bool;
    public function disablePlugin(string $pluginId): bool;

    // Dependency Management
    public function checkDependencies(string $pluginId): bool;
    public function getDependents(string $pluginId): array;

    // Discovery System
    public function discoverPlugins(): void;
    protected function loadAvailablePlugins(): void;
}
```

### 3. DependencyResolver (Topological Sorting)
**Location**: `packages/ns-plugins/src/Services/DependencyResolver.php`

```php
class DependencyResolver
{
    public function resolve(array $plugins): array {
        $graph = $this->buildDependencyGraph($plugins);

        if ($this->hasCycle($graph)) {
            throw new \RuntimeException('Circular dependency detected in plugins');
        }

        return $this->topologicalSort($graph);
    }

    protected function topologicalSort(array $graph): array;
    protected function buildDependencyGraph(array $plugins): array;
    protected function hasCycle(array $graph): bool;
}
```

## 🔧 Plugin Implementation Guide

### 1. Creating a New Plugin

```php
<?php

namespace Ns\ExampleFeature;

use Filament\Panel;
use Ns\Plugins\BaseFilamentPlugin;

class ExampleFeaturePlugin extends BaseFilamentPlugin
{
    /**
     * Plugin dependencies (loaded first)
     */
    protected array $dependencies = ['ns-core'];

    /**
     * Plugin configuration
     */
    protected array $config = [
        'version' => '1.0.0',
    ];

    public function getId(): string
    {
        return 'ns-example-feature';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            ExampleResource::class,
        ]);
    }

    protected function registerPages(Panel $panel): void
    {
        $panel->pages([
            ExamplePage::class,
        ]);
    }

    protected function registerWidgets(Panel $panel): void
    {
        $panel->widgets([
            ExampleWidget::class,
        ]);
    }

    protected function registerNavigationItems(Panel $panel): void
    {
        $panel->navigationGroups([
            'Example Category' => [
                'icon' => 'heroicon-o-star',
                'collapsible' => true,
            ],
        ]);
    }

    protected function bootPlugin(Panel $panel): void
    {
        // Custom boot logic
        // Register services, event listeners, etc.
    }
}
```

### 2. Directory Structure
```
packages/ns-example-feature/
├── src/
│   ├── ExampleFeaturePlugin.php          # Main plugin class
│   ├── ExampleServiceProvider.php        # Laravel service provider
│   ├── Filament/
│   │   ├── Resources/
│   │   │   └── ExampleResource.php
│   │   ├── Pages/
│   │   │   └── ExamplePage.php
│   │   └── Widgets/
│   │       └── ExampleWidget.php
│   ├── Models/
│   ├── Services/
│   └── Console/Commands/
├── config/
├── database/migrations/
├── resources/views/
└── composer.json
```

## 📊 Plugin Management UI

### 1. Plugin Statistics Widget
- **Available Plugins**: Count of plugins implementing Plugin interface
- **Enabled Plugins**: Currently active plugins
- **Disabled Plugins**: Inactive plugins
- **Legacy Plugins**: Need Plugin interface conversion

### 2. Plugin Activity Widget
- **Load Order**: Shows plugin loading sequence in dependency order
- **Plugin Status**: Visual indicators (Ready/Legacy)
- **System Summary**: Overview of plugin system health

### 3. Management Page (`/admin/plugins`)
- **Plugin List**: All plugins with enable/disable toggles
- **Dependency Validation**: Prevents breaking dependencies
- **Installation Interface**: Install new plugins
- **Configuration Access**: Plugin-specific settings

## 🔍 Database Schema

### installed_plugins Table
```sql
CREATE TABLE installed_plugins (
    id INTEGER PRIMARY KEY,
    name VARCHAR NOT NULL,                    -- Plugin ID (ns-core, ns-dns, etc.)
    plugin_class VARCHAR,                     -- Full PHP class name
    package_name VARCHAR,                     -- Composer package name
    path VARCHAR NOT NULL,                    -- Plugin file path
    namespace VARCHAR NOT NULL,               -- PHP namespace
    is_enabled BOOLEAN DEFAULT true,          -- Enable/disable status
    enabled BOOLEAN DEFAULT true,             -- Legacy column
    version VARCHAR NOT NULL,                 -- Plugin version
    description TEXT,                         -- Plugin description
    author VARCHAR,                           -- Plugin author
    config JSON,                              -- Plugin configuration
    dependencies JSON,                        -- Plugin dependencies array
    source VARCHAR DEFAULT 'local',          -- Installation source
    source_url VARCHAR,                       -- Source URL if remote
    installation_method VARCHAR DEFAULT 'manual',
    category VARCHAR,                         -- Plugin category
    composer_data JSON,                       -- Composer metadata
    installed_at TIMESTAMP,                  -- Installation timestamp
    last_updated_at TIMESTAMP,               -- Last update timestamp
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_enabled_category (is_enabled, category),
    UNIQUE KEY unique_package (package_name)
);
```

## 🚀 Plugin Discovery Process

### 1. Automatic Discovery
```php
// In AdminPanelProvider::registerEnabledPlugins()
$registry = app(PluginRegistry::class);
$enabledPluginIds = $registry->getEnabledPluginsInOrder();

$plugins = [];
foreach ($enabledPluginIds as $pluginId) {
    $pluginClass = $registry->getPluginClass($pluginId);
    if ($pluginClass && class_exists($pluginClass)) {
        $plugins[] = method_exists($pluginClass, 'make')
            ? $pluginClass::make()
            : new $pluginClass();
    }
}

if (!empty($plugins)) {
    $panel->plugins($plugins);
}
```

### 2. Plugin Loading Order
1. **Discovery**: `PluginRegistry` scans for Plugin interface implementations
2. **Dependency Resolution**: `DependencyResolver` creates topological order
3. **Registration**: Plugins register resources, pages, widgets in order
4. **Boot Phase**: Services and routes initialized

## 🔒 Security & Validation

### 1. Plugin Validation
- **Interface Check**: Must implement `Filament\Contracts\Plugin`
- **Class Verification**: Uses `ReflectionClass` to validate non-abstract classes
- **Dependency Validation**: Prevents circular dependencies
- **Permission Checking**: Admin-only plugin management

### 2. Error Handling
- **Graceful Degradation**: Failed plugins don't break the system
- **Logging**: All plugin operations logged for debugging
- **User Feedback**: Clear error messages in admin interface

## ⚡ Performance Optimizations

### 1. Caching Strategy
- **Plugin Order Cache**: 5-minute TTL for dependency resolution
- **Lazy Loading**: Plugins only loaded when enabled
- **Asset Optimization**: CSS/JS bundled per plugin

### 2. Memory Management
- **Reflection Caching**: Plugin class information cached
- **Conditional Loading**: Disabled plugins skip resource registration

## 🧪 Testing Strategy

### 1. Unit Tests
```php
// Test plugin dependency resolution
it('resolves plugin dependencies correctly', function () {
    $resolver = new DependencyResolver();
    $plugins = [
        'plugin-b' => ['dependencies' => ['plugin-a']],
        'plugin-a' => ['dependencies' => []],
    ];

    expect($resolver->resolve($plugins))->toBe(['plugin-a', 'plugin-b']);
});

// Test circular dependency detection
it('detects circular dependencies', function () {
    $resolver = new DependencyResolver();
    $plugins = [
        'plugin-a' => ['dependencies' => ['plugin-b']],
        'plugin-b' => ['dependencies' => ['plugin-a']],
    ];

    expect(fn() => $resolver->resolve($plugins))
        ->toThrow(RuntimeException::class, 'Circular dependency detected');
});
```

### 2. Integration Tests
```php
// Test plugin enable/disable functionality
it('can enable and disable plugins', function () {
    $registry = app(PluginRegistry::class);

    expect($registry->enablePlugin('ns-core'))->toBeTrue();
    expect($registry->getEnabledPluginsInOrder())->toContain('ns-core');

    expect($registry->disablePlugin('ns-core'))->toBeTrue();
    expect($registry->getEnabledPluginsInOrder())->not->toContain('ns-core');
});
```

## 📈 Migration Progress

### ✅ Completed (4/23 plugins)
- **ns-core**: Foundation services and models ✅
- **ns-plugins**: Plugin management system ✅
- **ns-ssh**: SSH operations and connections ✅
- **ns-dns**: DNS zones and records ✅

### 🔄 Remaining (19/23 plugins)
- **Infrastructure**: ns-ssl, ns-ipam, ns-secrets, ns-wireguard
- **Operations**: ns-migration, ns-setup, ns-backup, ns-monitor, ns-config, ns-automation
- **Services**: ns-web, ns-mail, ns-database, ns-domain
- **Governance**: ns-audit, ns-compliance, ns-analytics
- **Platform**: ns-platform
- **Development**: ns-example

### 🎯 Conversion Template
Each plugin needs:
1. Implement `Plugin` interface instead of custom interface
2. Extend `BaseFilamentPlugin` for common functionality
3. Define dependencies in `$dependencies` array
4. Move resource registration to proper hook methods
5. Add plugin to `PluginRegistry::loadAvailablePlugins()`

## 🔧 Development Commands

### Plugin Management
```bash
# Enable plugin
php artisan tinker
>>> app(\Ns\Plugins\PluginRegistry::class)->enablePlugin('ns-dns')

# Disable plugin
>>> app(\Ns\Plugins\PluginRegistry::class)->disablePlugin('ns-dns')

# List enabled plugins in order
>>> app(\Ns\Plugins\PluginRegistry::class)->getEnabledPluginsInOrder()

# Clear plugin cache
>>> app(\Ns\Plugins\PluginRegistry::class)->clearCache()
```

### Testing
```bash
# Run plugin-specific tests
php artisan test packages/ns-plugins/tests

# Test admin panel access
curl -s http://localhost:8888/admin/plugins

# Verify routes
php artisan route:list --path=admin
```

## 🔮 Future Enhancements

### 1. Plugin Marketplace
- Remote plugin installation from repositories
- Version management and updates
- Plugin ratings and reviews
- Dependency conflict resolution

### 2. Enhanced Configuration
- Plugin-specific configuration interfaces
- Environment-based plugin activation
- A/B testing for plugin features
- Performance monitoring per plugin

### 3. Advanced Features
- Plugin sandboxing for security
- Hot-swapping plugins without restart
- Plugin-specific logging and metrics
- Custom plugin lifecycle hooks

---

## 📚 Additional Resources

- [Filament v4 Plugin Documentation](https://filamentphp.com/docs/4.x/panels/plugins)
- [NetServa Platform Architecture](./NETSERVA-PLATFORM-ARCHITECTURE.md)
- [Plugin Development Guide](./PLUGIN-DEVELOPMENT-GUIDE.md)
- [Testing Plugin Systems](./TESTING-PLUGIN-SYSTEMS.md)

---

**Status**: ✅ Production Ready - 4 plugins converted, 19 remaining
**Last Updated**: 2025-09-16
**Version**: 1.0.0