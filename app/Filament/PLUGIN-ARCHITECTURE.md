# NetServa 3.0 - Filament 4.1 Plugin Architecture

**Version:** 1.0.0
**Date:** 2025-10-08
**Compliance:** Filament 4.1 Plugin Interface

---

## Overview

NetServa 3.0 implements a modular plugin architecture using Filament 4.1's `Plugin` interface. All 11 NetServa packages provide Filament resources through dedicated Plugin classes that extend `BaseFilamentPlugin`.

### Key Benefits

- **Modular Architecture:** Each package is a self-contained plugin with its own resources
- **Dependency Management:** Automatic dependency resolution and load ordering
- **Dynamic Registration:** Plugins can be enabled/disabled without code changes
- **Centralized Control:** All plugins managed through `PluginRegistry`
- **Consistent Pattern:** All plugins follow the same structure and conventions

---

## Plugin Ecosystem

### All NetServa Plugins (11 total)

| Plugin ID | Package | Resources | Dependencies | Description |
|-----------|---------|-----------|--------------|-------------|
| `netserva-core` | netserva-core | 3 + 1 widget | None | Foundation: system services, vhosts, mount points |
| `netserva-cli` | netserva-cli | 5 | core | CLI commands, migrations, templates |
| `netserva-cron` | netserva-cron | 2 | core | Automation jobs and tasks |
| `netserva-fleet` | netserva-fleet | 3 | core | Fleet VNodes, VHosts, VSites |
| `netserva-ipam` | netserva-ipam | 3 | core | IP networks, addresses, reservations |
| `netserva-wg` | netserva-wg | 2 | core, ipam | WireGuard servers and peers |
| `netserva-dns` | netserva-dns | 5 | core | DNS zones, records, providers, domains |
| `netserva-web` | netserva-web | 5 | core, dns | Virtual hosts, web servers, SSL certificates |
| `netserva-mail` | netserva-mail | 6 | core, dns | Mailboxes, domains, servers, queues |
| `netserva-config` | netserva-config | 9 | core | Templates, profiles, databases, secrets |
| `netserva-ops` | netserva-ops | 12 | core | Backups, monitoring, analytics, incidents |

**Total:** 53 Filament Resources + 1 Widget

---

## Architecture Pattern

### BaseFilamentPlugin Abstract Class

All NetServa plugins extend `NetServa\Core\Foundation\BaseFilamentPlugin`:

```php
abstract class BaseFilamentPlugin implements Plugin
{
    protected array $dependencies = [];

    abstract public function getId(): string;
    abstract protected function registerResources(Panel $panel): void;
    abstract protected function registerPages(Panel $panel): void;
    abstract protected function registerWidgets(Panel $panel): void;
    abstract protected function registerNavigationItems(Panel $panel): void;

    public function register(Panel $panel): void;
    public function boot(Panel $panel): void;
    public function getDependencies(): array;
    public function getVersion(): string;
    public function getDefaultConfig(): array;
}
```

### Standard Plugin Structure

Every NetServa plugin follows this pattern:

```php
<?php

namespace NetServa\PackageName\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\PackageName\Filament\Resources\ResourceOneResource;
use NetServa\PackageName\Filament\Resources\ResourceTwoResource;

class NetServaPackagePlugin extends BaseFilamentPlugin
{
    // Declare dependencies
    protected array $dependencies = ['netserva-core'];

    // Unique plugin ID
    public function getId(): string
    {
        return 'netserva-packagename';
    }

    // Register Filament resources
    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            ResourceOneResource::class,
            ResourceTwoResource::class,
        ]);
    }

    // Register custom pages (optional)
    protected function registerPages(Panel $panel): void
    {
        // $panel->pages([...]);
    }

    // Register widgets (optional)
    protected function registerWidgets(Panel $panel): void
    {
        // $panel->widgets([...]);
    }

    // Configure navigation groups and ordering
    protected function registerNavigationItems(Panel $panel): void
    {
        ResourceOneResource::$navigationGroup = 'Group Name';
        ResourceOneResource::$navigationSort = 10;

        ResourceTwoResource::$navigationGroup = 'Group Name';
        ResourceTwoResource::$navigationSort = 20;
    }

    // Plugin version (semantic versioning)
    public function getVersion(): string
    {
        return '3.0.0';
    }

    // Default plugin configuration
    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [
                'feature_one' => true,
                'feature_two' => true,
            ],
            'settings' => [
                'setting_key' => 'default_value',
            ],
        ];
    }
}
```

---

## Navigation Organization

### Navigation Groups by Plugin

Each plugin organizes its resources into logical navigation groups:

**netserva-core:**
- `System` - Core infrastructure resources

**netserva-cli:**
- `CLI Management` - Command-line interface resources

**netserva-cron:**
- `Automation` - Scheduled tasks and jobs

**netserva-fleet:**
- `Fleet Management` - VNode/VHost/VSite resources

**netserva-ipam:**
- `IP Address Management` - Network and IP resources

**netserva-wg:**
- `VPN Services` - WireGuard management

**netserva-dns:**
- `DNS & Domains` - DNS zones, records, registrations

**netserva-web:**
- `Web Services` - Virtual hosts, web servers, SSL

**netserva-mail:**
- `Mail Services` - Mailboxes, domains, servers

**netserva-config:**
- `Configuration` - Templates, profiles, variables (4)
- `Databases` - Database resources (3)
- `Secrets` - Secret management (2)

**netserva-ops:**
- `Backups` - Backup management (3)
- `Monitoring` - Checks, alerts, incidents (4)
- `Analytics` - Metrics, dashboards, visualizations (5)

---

## Dependency Management

### Dependency Graph

```
netserva-core (foundation)
├── netserva-cli
├── netserva-cron
├── netserva-fleet
├── netserva-ipam
│   └── netserva-wg
├── netserva-dns
│   ├── netserva-web
│   └── netserva-mail
├── netserva-config
└── netserva-ops
```

### Dependency Resolution

The `PluginRegistry` automatically:
1. Validates all dependencies are available
2. Resolves dependency order using `DependencyResolver`
3. Loads plugins in correct sequence
4. Prevents circular dependencies
5. Blocks disabling plugins with active dependents

### Declaring Dependencies

In your Plugin class:

```php
protected array $dependencies = ['netserva-core', 'netserva-dns'];
```

Dependencies are:
- Automatically validated before plugin activation
- Used to determine plugin load order
- Enforced when disabling plugins

---

## Plugin Registry

### Location

`packages/netserva-core/src/Foundation/PluginRegistry.php`

### Available Methods

```php
// Discovery
$registry->getAvailablePluginIds(): array
$registry->getAvailablePlugins(): array
$registry->getPluginClass(string $pluginId): ?string
$registry->hasPlugin(string $pluginId): bool

// Installation & Activation
$registry->installPlugin(string $pluginId, bool $enable = true): bool
$registry->uninstallPlugin(string $pluginId): bool
$registry->enablePlugin(string $pluginId): bool
$registry->disablePlugin(string $pluginId): bool

// Querying
$registry->getEnabledPluginsInOrder(): array
$registry->getEnabledPlugins(): Collection
$registry->getDisabledPlugins(): Collection
$registry->getUninstalledPlugins(): array

// Dependencies
$registry->checkDependencies(string $pluginId): bool
$registry->getDependents(string $pluginId): array

// Utilities
$registry->discoverPlugins(): void
$registry->clearCache(): void
```

### Plugin Storage

Plugins are stored in the `installed_plugins` database table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Plugin ID (e.g., 'netserva-core') |
| `plugin_class` | string | Fully qualified class name |
| `is_enabled` | boolean | Active status |
| `version` | string | Plugin version (semantic) |
| `config` | json | Plugin-specific configuration |
| `created_at` | timestamp | Installation time |
| `updated_at` | timestamp | Last modification |

---

## Managing Plugins

### Via PluginRegistry

```php
use NetServa\Core\Foundation\PluginRegistry;

$registry = app(PluginRegistry::class);

// List available plugins
$available = $registry->getAvailablePluginIds();
// ['netserva-core', 'netserva-cli', 'netserva-dns', ...]

// Install and enable plugin
$registry->installPlugin('netserva-mail', enable: true);

// Check dependencies
if ($registry->checkDependencies('netserva-web')) {
    $registry->enablePlugin('netserva-web');
}

// Get enabled plugins in dependency order
$plugins = $registry->getEnabledPluginsInOrder();

// Disable plugin (checks for dependents)
$registry->disablePlugin('netserva-ipam');
// Returns false if netserva-wg is enabled (depends on ipam)

// Get plugins depending on this one
$dependents = $registry->getDependents('netserva-core');
// Returns all plugins (all depend on core)
```

### Via Filament Panel

Plugins are automatically registered in the Filament panel via the `PanelProvider`:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugins([
            // Plugins loaded automatically from PluginRegistry
            ...$this->getEnabledPlugins(),
        ]);
}

protected function getEnabledPlugins(): array
{
    $registry = app(PluginRegistry::class);
    $enabledClasses = $registry->getEnabledPluginsInOrder();

    return array_map(fn($class) => new $class, $enabledClasses);
}
```

---

## Creating a New Plugin

### Step 1: Create Plugin Class

**File:** `packages/your-package/src/Filament/YourPackagePlugin.php`

```php
<?php

namespace NetServa\YourPackage\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\YourPackage\Filament\Resources\YourResource;

class YourPackagePlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-yourpackage';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            YourResource::class,
        ]);
    }

    protected function registerPages(Panel $panel): void
    {
        // Optional: register custom pages
    }

    protected function registerWidgets(Panel $panel): void
    {
        // Optional: register widgets
    }

    protected function registerNavigationItems(Panel $panel): void
    {
        YourResource::$navigationGroup = 'Your Group';
        YourResource::$navigationSort = 10;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [],
            'settings' => [],
        ];
    }
}
```

### Step 2: Register in PluginRegistry

Edit `packages/netserva-core/src/Foundation/PluginRegistry.php`:

```php
$potentialPlugins = [
    // ... existing plugins ...
    'netserva-yourpackage' => \NetServa\YourPackage\Filament\YourPackagePlugin::class,
];
```

### Step 3: Add Composer Dependencies

**File:** `packages/your-package/composer.json`

```json
{
    "require": {
        "netserva/core": "*"
    }
}
```

### Step 4: Install & Enable

```php
$registry = app(PluginRegistry::class);
$registry->installPlugin('netserva-yourpackage', enable: true);
```

---

## Testing Plugins

### Unit Tests

Test plugin structure and configuration:

```php
use NetServa\YourPackage\Filament\YourPackagePlugin;

describe('YourPackagePlugin', function () {
    it('has correct plugin ID', function () {
        $plugin = new YourPackagePlugin;
        expect($plugin->getId())->toBe('netserva-yourpackage');
    });

    it('declares correct dependencies', function () {
        $plugin = new YourPackagePlugin;
        expect($plugin->getDependencies())->toBe(['netserva-core']);
    });

    it('has semantic version', function () {
        $plugin = new YourPackagePlugin;
        expect($plugin->getVersion())->toMatch('/^\d+\.\d+\.\d+$/');
    });

    it('provides default config', function () {
        $plugin = new YourPackagePlugin;
        $config = $plugin->getDefaultConfig();

        expect($config)->toHaveKeys(['version', 'enabled_features', 'settings']);
    });
});
```

### Integration Tests

Test plugin registration and dependencies:

```php
use NetServa\Core\Foundation\PluginRegistry;

describe('Plugin Integration', function () {
    it('registers plugin in registry', function () {
        $registry = app(PluginRegistry::class);
        expect($registry->hasPlugin('netserva-yourpackage'))->toBeTrue();
    });

    it('validates dependencies before enabling', function () {
        $registry = app(PluginRegistry::class);

        // Disable dependency
        $registry->disablePlugin('netserva-core');

        // Try to enable plugin
        $result = $registry->enablePlugin('netserva-yourpackage');

        expect($result)->toBeFalse();
    });
});
```

---

## Best Practices

### Plugin Design

1. **Single Responsibility:** Each plugin should manage one domain (DNS, mail, web, etc.)
2. **Dependency Minimum:** Only declare essential dependencies
3. **Configuration Defaults:** Always provide sensible defaults
4. **Navigation Grouping:** Group related resources together
5. **Semantic Versioning:** Follow semver for version numbers

### Resource Organization

```php
// ✅ DO: Group related resources in same navigation group
MailboxResource::$navigationGroup = 'Mail Services';
MailDomainResource::$navigationGroup = 'Mail Services';
MailServerResource::$navigationGroup = 'Mail Services';

// ❌ DON'T: Scatter related resources across groups
MailboxResource::$navigationGroup = 'Email';
MailDomainResource::$navigationGroup = 'Domains';
MailServerResource::$navigationGroup = 'Servers';
```

### Navigation Sorting

```php
// Use increments of 10 for easy insertion
ResourceOne::$navigationSort = 10;
ResourceTwo::$navigationSort = 20;
ResourceThree::$navigationSort = 30;

// Within complex plugins, use logical grouping
// Backups
BackupJobResource::$navigationSort = 10;
BackupRepositoryResource::$navigationSort = 20;

// Monitoring (start at next group)
MonitoringCheckResource::$navigationSort = 40;
AlertRuleResource::$navigationSort = 50;
```

### Version Management

```php
// ✅ DO: Use semantic versioning
public function getVersion(): string
{
    return '3.0.0';  // MAJOR.MINOR.PATCH
}

// ❌ DON'T: Use arbitrary version strings
return 'v3';
return '2024-10-08';
```

### Configuration Schema

```php
public function getDefaultConfig(): array
{
    return [
        'version' => $this->getVersion(),

        // Feature toggles
        'enabled_features' => [
            'feature_name' => true,
        ],

        // Plugin settings
        'settings' => [
            'setting_key' => 'default_value',
        ],
    ];
}
```

---

## Migration Guide

### From Resources to Plugins

If you have existing Filament Resources not yet in a Plugin:

1. **Create Plugin class** following the standard structure
2. **Move resource registration** from PanelProvider to Plugin
3. **Set navigation properties** in `registerNavigationItems()`
4. **Declare dependencies** in `$dependencies` array
5. **Register in PluginRegistry**
6. **Update composer.json** with package dependencies
7. **Test plugin installation** and activation

---

## Troubleshooting

### Plugin Not Loading

**Symptom:** Plugin not appearing in Filament panel

**Solutions:**
1. Check plugin is registered in `PluginRegistry`
2. Verify class name and namespace are correct
3. Ensure plugin is enabled: `$registry->enablePlugin('plugin-id')`
4. Clear cache: `$registry->clearCache()`
5. Check dependencies are satisfied

### Dependency Errors

**Symptom:** Plugin fails to enable due to dependencies

**Solutions:**
1. Check required plugins are installed and enabled
2. Verify dependency names match plugin IDs exactly
3. Review dependency graph for conflicts
4. Enable dependencies first, then dependent plugin

### Resources Not Appearing

**Symptom:** Resources registered but not visible

**Solutions:**
1. Verify `registerResources()` calls `$panel->resources([...])`
2. Check resource classes exist and are importable
3. Ensure navigation groups are set in `registerNavigationItems()`
4. Clear Filament cache: `php artisan filament:cache:clear`

---

## References

- **Filament Docs:** https://filamentphp.com/docs/4.x/panels/plugins
- **BaseFilamentPlugin:** `packages/netserva-core/src/Foundation/BaseFilamentPlugin.php`
- **PluginRegistry:** `packages/netserva-core/src/Foundation/PluginRegistry.php`
- **Example Plugin:** `packages/netserva-dns/src/Filament/NetServaDnsPlugin.php`

---

**Document Version:** 1.0.0
**Last Updated:** 2025-10-08
**Maintained By:** NetServa Development Team
