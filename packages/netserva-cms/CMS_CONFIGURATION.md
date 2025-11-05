# NetServa CMS Configuration Guide

## Site Identity Configuration

The CMS site name, tagline, and branding are configured via the package config file, **NOT** via Laravel's `APP_NAME`.

### Configuration File

`config/netserva-cms.php` (auto-loaded by the package)

### Default Values (Built into Package)

```php
'name' => 'NetServa',
'tagline' => 'Server Management Platform',
'description' => 'Modern server management platform built on Laravel 12 and Filament 4',
```

### Customization Options

#### Option 1: Environment Variables (Recommended for Deployment)
Add to your `.env` file:
```env
CMS_NAME="Your Company Name"
CMS_TAGLINE="Your Tagline"
CMS_DESCRIPTION="Your description"
```

#### Option 2: Publish and Edit Config (Permanent Customization)
```bash
php artisan vendor:publish --tag=netserva-cms-config
```

Then edit `config/netserva-cms.php` directly.

### Where It's Used

The site name appears in:
- **Navigation header** (desktop & mobile)
- **Footer copyright**
- **SEO meta tags** (title, og:title, twitter:title)
- **Footer branding**

### How It Works Across Installations

**Monorepo (`~/.ns/`)**:
- Uses package defaults OR custom `.env` settings
- Can publish config for permanent customization

**Standalone Installations** (like `test-alacarte`):
- Automatically gets package defaults
- Works immediately without configuration
- Can be customized via `.env` or published config

### Benefits Over `APP_NAME`

✅ **Separation of concerns**: Laravel app name ≠ CMS site name
✅ **Persistent defaults**: Package includes sensible defaults
✅ **Portable**: Same config works in monorepo and standalone
✅ **Version controlled**: Defaults are in the package
✅ **Customizable**: Easy to override per installation

### Example Configurations

**NetServa (default)**:
```env
# No .env needed - uses package defaults
```

**Custom Deployment**:
```env
CMS_NAME="Acme Corporation"
CMS_TAGLINE="Building the Future"
CMS_DESCRIPTION="Leading provider of cloud infrastructure solutions"
CMS_SITE_NAME="Acme Corp"
CMS_TWITTER_HANDLE="@acmecorp"
```

### Full Configuration Options

See `config/netserva-cms.php` for all available options:
- Site identity (name, tagline, description)
- SEO settings (meta titles, descriptions, social)
- Frontend settings (theme, caching)
- Blog settings (pagination, categories, tags)
- Media settings (upload limits, conversions)
- Template options

### Testing Configuration

```bash
# Check current site name
php artisan tinker --execute="echo config('netserva-cms.name') . PHP_EOL;"

# Check full config
php artisan tinker --execute="print_r(config('netserva-cms'));"
```

### Upgrade Notes

If upgrading from older versions that used `config('app.name')`:
- ✅ Already migrated in latest version
- No action needed - package now uses `config('netserva-cms.name')`
