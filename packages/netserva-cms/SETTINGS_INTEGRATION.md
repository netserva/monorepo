# Settings Integration Guide

## Overview

NetServa CMS now supports **progressive enhancement** for settings management:

- **Standalone Mode**: Uses config files + `.env` overrides (read-only)
- **Enhanced Mode**: Uses Spatie Settings via `netserva-core` (database-backed, CRUD UI)

## Architecture

```
┌─────────────────────────────────┐
│ netserva-core                   │
│ ├─ spatie/laravel-settings      │
│ └─ Foundation for all plugins   │
└─────────────────────────────────┘
             ↓ (optional dependency)
┌─────────────────────────────────┐
│ netserva-cms (or any plugin)    │
│ ├─ Works standalone             │
│ ├─ Detects if core installed    │
│ └─ Auto-enables enhanced mode   │
└─────────────────────────────────┘
```

## How It Works

### Standalone Mode (Default)

```bash
composer require netserva/cms
```

- Settings read from `config/netserva-cms.php`
- Can override via `.env` (e.g., `CMS_NAME="My Site"`)
- Read-only (no UI for editing)
- `cms_setting('name')` returns config value

### Enhanced Mode (with netserva-core)

```bash
composer require netserva/cms netserva/core
php artisan migrate
```

- Settings stored in database
- Filament admin UI for editing
- Type-safe with validation
- `cms_setting('name')` returns database value
- Config values become migration defaults

## Implementation Pattern

### 1. Settings Manager (Auto-Detection)

```php
// packages/netserva-cms/src/Support/SettingsManager.php
class SettingsManager
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::hasSpatieSettings()) {
            return static::getSpatieValue($key, $default);
        }
        return config("netserva-cms.{$key}", $default);
    }
}
```

### 2. Settings Class (Only if Spatie Available)

```php
// packages/netserva-cms/src/Settings/CmsSettings.php
class CmsSettings extends Settings
{
    public string $name;
    public string $tagline;

    public static function group(): string
    {
        return 'cms';
    }

    public static function defaults(): array
    {
        return [
            'name' => config('netserva-cms.name', 'NetServa'),
            'tagline' => config('netserva-cms.tagline', '...'),
        ];
    }
}
```

### 3. Helper Function

```php
// packages/netserva-cms/src/helpers.php
function cms_setting(string $key, mixed $default = null): mixed
{
    return SettingsManager::get($key, $default);
}
```

### 4. Migration (Conditional Loading)

```php
// packages/netserva-cms/database/settings/2025_11_05_create_cms_settings.php
public function up(): void
{
    $defaults = \NetServa\Cms\Settings\CmsSettings::defaults();

    foreach ($defaults as $key => $value) {
        $this->migrator->add("cms.{$key}", $value);
    }
}
```

### 5. Filament Settings Page

```php
// packages/netserva-cms/src/Filament/Pages/ManageCmsSettings.php
class ManageCmsSettings extends SettingsPage
{
    protected static string $settings = CmsSettings::class;

    public static function shouldRegisterNavigation(): bool
    {
        return class_exists(\Spatie\LaravelSettings\Settings::class);
    }
}
```

### 6. Service Provider (Conditional Loading)

```php
public function register(): void
{
    require_once __DIR__.'/helpers.php';
}

public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    // Only load if Spatie Settings available
    if (class_exists(\Spatie\LaravelSettings\Settings::class)) {
        $this->loadMigrationsFrom(__DIR__.'/../database/settings');
    }
}
```

## Which Plugins Should Use This Pattern?

### ✅ Should Implement Settings

**netserva-cms** (✅ IMPLEMENTED)
- Site name, tagline, description
- Logo, favicon
- Contact information
- Localization (timezone, locale)

**netserva-dns** (RECOMMENDED)
- Default nameservers (ns1.example.com, ns2.example.com)
- Default TTL values (SOA, A, AAAA, MX, etc.)
- SOA defaults (refresh, retry, expire, minimum)
- DNSSEC settings

**netserva-mail** (RECOMMENDED)
- Default mailbox quota
- SPAM filter thresholds
- Default mail client settings (IMAP/SMTP ports)
- Retention policies

**netserva-web** (RECOMMENDED)
- Default PHP version
- Default web server (nginx, apache)
- SSL certificate defaults
- Resource limits (upload size, memory limit)

### ❌ Should NOT Use Settings

**netserva-config** - Already provides ConfigVariable for complex configs
**netserva-core** - Foundation layer, no user-facing settings
**netserva-fleet** - Infrastructure data, not settings
**netserva-ops** - Monitoring/analytics configs (complex)
**netserva-cli** - CLI tool, no UI needed

## Migration Path for Existing Installations

```bash
# Before (standalone CMS)
composer require netserva/cms
# Settings in config/netserva-cms.php + .env

# Add enhanced features
composer require netserva/core
php artisan migrate
# Creates settings table
# Populates from existing config values
# Settings now editable via Filament admin
```

## Benefits

✅ **Zero breaking changes** - Existing code continues working
✅ **Progressive enhancement** - Add features when needed
✅ **Standalone deployable** - Plugins work without dependencies
✅ **Type-safe** - Spatie provides typed properties
✅ **UI auto-appears** - Only when enhanced mode available
✅ **Config becomes migration** - Existing values preserved

## Testing Both Modes

```php
// Test standalone mode
SettingsManager::resetCache();
expect(cms_setting('name'))->toBe('NetServa');

// Test enhanced mode (if netserva-core installed)
if (class_exists(\Spatie\LaravelSettings\Settings::class)) {
    $settings = app(CmsSettings::class);
    $settings->name = 'Custom Name';
    $settings->save();

    expect(cms_setting('name'))->toBe('Custom Name');
}
```

## Files Created

```
packages/netserva-cms/
├── src/
│   ├── Support/
│   │   └── SettingsManager.php          ← Auto-detection logic
│   ├── Settings/
│   │   └── CmsSettings.php              ← Settings class (requires Spatie)
│   ├── Filament/Pages/
│   │   └── ManageCmsSettings.php        ← Admin UI (auto-hides if standalone)
│   └── helpers.php                       ← cms_setting() helper
├── database/settings/
│   └── 2025_11_05_create_cms_settings.php  ← Migration (conditional)
└── composer.json                         ← "suggest": {"netserva/core": "..."}

packages/netserva-core/
└── composer.json                         ← "require": {"spatie/laravel-settings": "^3.0"}
```

## Usage Examples

### In Controllers

```php
public function index()
{
    return view('welcome', [
        'siteName' => cms_setting('name'),
        'tagline' => cms_setting('tagline'),
    ]);
}
```

### In Blade Views

```blade
<h1>{{ cms_setting('name') }}</h1>
<p>{{ cms_setting('tagline') }}</p>

{{-- Check if enhanced mode --}}
@if (cms_settings_enhanced())
    <a href="{{ route('filament.admin.pages.manage-cms-settings') }}">
        Edit Settings
    </a>
@endif
```

### In Config Files

```php
return [
    'name' => env('CMS_NAME', 'NetServa'),
    'tagline' => env('CMS_TAGLINE', 'Server Management Platform'),
];
```

### In Migrations

```php
$defaults = \NetServa\Cms\Settings\CmsSettings::defaults();

foreach ($defaults as $key => $value) {
    $this->migrator->add("cms.{$key}", $value);
}
```

## Next Steps for Other Plugins

To add settings to other plugins (DNS, Mail, Web):

1. Copy `netserva-cms/src/Support/SettingsManager.php` → `netserva-dns/src/Support/SettingsManager.php`
2. Create `netserva-dns/src/Settings/DnsSettings.php` with DNS-specific properties
3. Create `netserva-dns/src/helpers.php` with `dns_setting()` helper
4. Create `netserva-dns/database/settings/` migration
5. Create `netserva-dns/src/Filament/Pages/ManageDnsSettings.php`
6. Update service provider to load helpers + conditional migrations
7. Add `"suggest": {"netserva/core": "..."}` to composer.json

---

**Version**: 1.0.0
**Date**: 2025-11-05
**Status**: CMS Implementation Complete, DNS/Mail/Web Recommended
