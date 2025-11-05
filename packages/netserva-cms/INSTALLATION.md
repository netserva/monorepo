# NetServa CMS Installation Guide

## Quick Start (Automated)

```bash
# Create new Laravel project
composer create-project laravel/laravel my-cms-site
cd my-cms-site

# Install CMS
composer require netserva/cms

# Run automated setup
php artisan netserva-cms:install --seed --force

# IMPORTANT: Install typography plugin for proper content formatting
npm install -D @tailwindcss/typography

# Add to resources/css/app.css (after line 1):
# @plugin '@tailwindcss/typography';

# Build assets
npm install && npm run build

# Start server
php artisan serve
```

Visit http://localhost:8000/admin (admin@netserva.com / password)

---

## Composer Version Constraints

### Getting Latest Version

```bash
# Best practice - gets latest stable version
composer require netserva/cms

# Equivalent alternatives
composer require netserva/cms:*
composer require netserva/cms:@stable
```

### Version Constraints

| Constraint | Meaning | Example Versions Installed |
|------------|---------|----------------------------|
| `netserva/cms` | Latest stable | 0.0.3, 1.0.0, 2.5.1, etc. |
| `netserva/cms:^0.0.3` | 0.0.x only | 0.0.3, 0.0.4, 0.0.5 (NOT 0.1.0) |
| `netserva/cms:~0.0.3` | 0.0.3+ | 0.0.3, 0.0.4 (same as ^) |
| `netserva/cms:>=0.0.3` | 0.0.3 or higher | 0.0.3, 1.0.0, 2.0.0, etc. |
| `netserva/cms:*` | Any version | Same as no constraint |
| `netserva/cms:@dev` | Development branch | dev-main |

**Recommendation for Scripts**: Use no version constraint (`composer require netserva/cms`) to always get the latest stable release.

---

## Manual Installation Steps

If you prefer to understand what's happening, here are the manual steps:

### 1. Install Package

```bash
composer require netserva/cms
```

### 2. Root Route Handling (Automatic)

**No action required!** The CMS package automatically removes Laravel's default welcome route when installed.

If you want to disable the CMS frontend and use your own root route instead:

```php
// config/netserva-cms.php
return [
    'frontend' => [
        'enabled' => false, // Disables CMS root route
    ],
];
```

Then define your own routes in `routes/web.php` as normal.

### 3. Register Seeder

Edit `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    // Your existing seeders...

    // Call CMS package seeder
    $this->call(\NetServa\Cms\Database\Seeders\NetServaCmsSeeder::class);
}
```

### 4. Run Migrations & Seeders

```bash
php artisan migrate --force
php artisan db:seed --class="NetServa\Cms\Database\Seeders\NetServaCmsSeeder"
```

### 5. Install Filament Admin Panel

```bash
php artisan filament:install --panels --no-interaction
```

### 6. Configure Admin Panel

Edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        // ... existing config ...
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
        ->discoverResources(in: base_path('vendor/netserva/cms/src/Filament/Resources'), for: 'NetServa\Cms\Filament\Resources')
        // ... rest of config ...
}
```

### 7. Create Admin User

```bash
php artisan tinker --execute="
use Illuminate\Support\Facades\Hash;
\App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@netserva.com',
    'password' => Hash::make('password')
]);
"
```

### 8. Build Frontend Assets (CRITICAL)

**⚠️ IMPORTANT:** The CMS requires the Tailwind Typography plugin for proper content formatting.

1. **Install dependencies including typography plugin:**

Update your `package.json` to include:
```json
{
  "devDependencies": {
    "@tailwindcss/typography": "^0.5.19",
    "@tailwindcss/vite": "^4.0.0",
    "laravel-vite-plugin": "^2.0.0",
    "tailwindcss": "^4.0.0",
    "vite": "^7.0.0"
  }
}
```

Then run:
```bash
npm install
```

2. **Configure Tailwind in `resources/css/app.css`:**

```css
@import 'tailwindcss';
@plugin '@tailwindcss/typography';  ← REQUIRED for proper content formatting

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

/* Configure dark mode to use class-based switching */
@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}

/* Fix double backgrounds in code blocks */
.prose pre code {
    background-color: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
    color: rgb(243 244 246) !important;
}

.dark .prose pre code {
    color: rgb(243 244 246) !important;
}
```

3. **Build assets:**

```bash
npm run build
```

**Without the typography plugin, all content will run together without proper spacing!**

### 9. Clear Caches

```bash
php artisan optimize:clear
```

---

## Post-Installation

### What Gets Installed

**Database Content:**
- 3 Pages (Home, About, Features)
- 12 Blog Posts (various topics)
- 3 Categories (News, Tutorials, Development)
- 6 Tags (Laravel, Filament, DNS, Mail, SSH, Nginx)
- 1 Menu (Main Navigation with 4 items)

**Admin Resources:**
- Pages Management
- Posts Management
- Categories Management
- Tags Management
- Menus Management

**Routes:**
- `/` - Homepage
- `/about` - About page
- `/features` - Features page
- `/blog` - Blog index
- `/blog/{slug}` - Individual posts
- `/admin` - Admin panel
- `/admin/login` - Admin login

### Default Credentials

- Email: `admin@netserva.com`
- Password: `password`

**⚠️ Change these immediately in production!**

---

## Automation Script

For fully automated installation, use the provided script:

```bash
# Download from package
cp vendor/netserva/cms/install-cms.sh .
chmod +x install-cms.sh

# Run interactively
./install-cms.sh

# Run non-interactively (CI/CD)
./install-cms.sh --non-interactive
```

---

## Architecture Notes

### Why Manual Steps Were Needed

The CMS package is designed to be **standalone** but **non-invasive**. Laravel packages cannot:

1. **Automatically modify application files** (security risk)
2. ~~**Override application routes** (could break existing apps)~~ ✅ **SOLVED!** The CMS now intelligently removes the default Laravel welcome route
3. **Auto-run seeders** (Laravel convention - must be explicit)
4. **Install other packages** (Filament) automatically

### How Automatic Root Route Handling Works

The CMS service provider intelligently handles the root route:

1. **Detection**: Checks if Laravel's default welcome route exists on `/`
2. **Removal**: Safely removes the default route if found
3. **Registration**: Registers the CMS homepage route
4. **Respect**: If you set `frontend.enabled => false` in config, the CMS won't touch any routes

This means:
- ✅ Fresh Laravel installs work immediately after `composer require netserva/cms`
- ✅ Existing apps can disable CMS frontend via config if needed
- ✅ No manual route file editing required
- ✅ Falls back gracefully if no welcome route exists

### What's Automated Now

**Automatic (no setup required):**
✅ Removing Laravel welcome route when CMS frontend is enabled
✅ Handling root `/` route with CMS homepage

**Via `php artisan netserva-cms:install` command:**
✅ Detecting and offering to remove Laravel welcome route (manual step now optional)
✅ Adding seeder call to DatabaseSeeder
✅ Running migrations
✅ Seeding default content
✅ Installing Filament panel
✅ Configuring admin panel to discover CMS resources
✅ Creating admin user
✅ Building frontend assets

### Seeder Content

**Q: Why is seeded content different from monorepo content?**

A: The seeder is **identical** in both locations. The content is generic "NetServa CMS" branding because:

1. **Standalone deployments** need generic content
2. **Monorepo integration** uses the same seeder
3. **Client projects** should override seeder with their own content

The seeder provides a **professional starting point**, not production content. Real deployments should:

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    if (app()->environment('local')) {
        // Use generic CMS seeder for testing
        $this->call(\NetServa\Cms\Database\Seeders\NetServaCmsSeeder::class);
    } else {
        // Use your own production seeder
        $this->call(ProductionContentSeeder::class);
    }
}
```

---

## Integration with NetServa Platform

The CMS package is designed to work **standalone** or as part of the **full NetServa platform**:

### Standalone Mode (Default)
- Runs independently
- Generic branding
- No platform dependencies

### Platform Mode (with netserva/core)
- Registers as NetServa plugin
- Integrates with settings system
- Uses platform branding
- Shares authentication

The package automatically detects if `netserva/core` is installed and enables platform features.

---

## Troubleshooting

### Content Has No Spacing/Formatting (All Text Runs Together)

**Problem:** The homepage and other pages show all content compressed together without proper spacing between headings, paragraphs, and lists. Sections like "Modern Server Management", "Key Capabilities", etc. run together.

**Cause:** Missing Tailwind Typography plugin in `resources/css/app.css`.

**Solution:**

1. Install the typography plugin:
```bash
npm install -D @tailwindcss/typography
```

2. Add to `resources/css/app.css` (add this as line 2):
```css
@import 'tailwindcss';
@plugin '@tailwindcss/typography';  ← ADD THIS LINE

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
```

3. Complete `resources/css/app.css` should look like:
```css
@import 'tailwindcss';
@plugin '@tailwindcss/typography';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

/* Configure dark mode to use class-based switching */
@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}

/* Fix double backgrounds in code blocks */
.prose pre code {
    background-color: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
    color: rgb(243 244 246) !important;
}

.dark .prose pre code {
    color: rgb(243 244 246) !important;
}
```

4. Rebuild assets:
```bash
npm run build
```

5. Refresh your browser (hard refresh: Ctrl+Shift+R or Cmd+Shift+R)

**Why this happens:** The CMS uses Tailwind's `prose` classes for content formatting. Without the `@tailwindcss/typography` plugin, these classes have no effect and all content runs together.

### I want to use my own root route, not the CMS

Disable the CMS frontend in your config:

```bash
php artisan vendor:publish --tag=netserva-cms-config
```

Edit `config/netserva-cms.php`:

```php
'frontend' => [
    'enabled' => false,
],
```

Then define your own routes in `routes/web.php`. The CMS will not interfere.

### 404 on /admin

```bash
php artisan optimize:clear
php artisan filament:upgrade
```

### Seeder Not Found

Make sure you're using the fully qualified class name:

```bash
php artisan db:seed --class="NetServa\Cms\Database\Seeders\NetServaCmsSeeder"
```

### Resources Not Showing in Admin

Check that `AdminPanelProvider.php` has:

```php
->discoverResources(in: base_path('vendor/netserva/cms/src/Filament/Resources'), for: 'NetServa\Cms\Filament\Resources')
```

Then clear cache:

```bash
php artisan optimize:clear
```

---

## Next Steps

1. **Change default password**: Log into admin and update user
2. **Customize branding**: Edit pages via admin panel
3. **Configure mail**: Set up SMTP in `.env`
4. **Add media**: Upload images via Filament media library
5. **Create content**: Add your own pages and posts
6. **Customize views**: Publish views with `php artisan vendor:publish --tag=netserva-cms-views`

---

## Support

- Documentation: https://github.com/netserva/monorepo/tree/main/packages/netserva-cms
- Issues: https://github.com/netserva/monorepo/issues
- Email: info@netserva.com
