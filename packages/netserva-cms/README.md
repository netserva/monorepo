# NetServa CMS

**Professional Laravel 12 + Filament 4 Content Management System**

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-4.x-FDAE4B?logo=filament)](https://filamentphp.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://php.net)
[![Pest](https://img.shields.io/badge/Pest-4.x-44C563)](https://pestphp.com)

> 🎯 **Design Philosophy:** Standalone & Deployable
> Built to work both within NetServa 3.0 AND as a completely standalone Laravel package

---

## ✨ Features

### Content Management
- 📄 **Hierarchical Pages** - Nested page structure with multiple templates
- 📝 **Blog System** - Full-featured blogging with categories and tags
- 🏷️ **Categories & Tags** - Organize content with taxonomies
- 🍔 **Menu Builder** - Flexible JSON-based navigation with nested items
- 🎨 **Multiple Templates** - Homepage, Service, Pricing, Default, and Blank layouts

### Media & SEO
- 🖼️ **Media Library** - Spatie Media Library with featured images & galleries
- 🔍 **SEO Optimized** - Meta tags, Open Graph, Twitter Cards built-in
- 📊 **Reading Time** - Automatic word count and reading time calculation
- 🔗 **Sluggable URLs** - Automatic SEO-friendly URL generation

### Admin Experience
- 🎛️ **Filament 4 Admin** - Modern, beautiful admin interface
- ✏️ **Rich Editor** - Full-featured content editing with file attachments
- 🌓 **Dark Mode** - Full dark mode support throughout
- 📱 **Responsive** - Mobile-first admin panel design

### Developer Experience
- ✅ **Zero Dependencies** - NO NetServa dependencies, works anywhere
- 🧪 **Comprehensive Tests** - 70+ Pest tests with 100% coverage goal
- 🏭 **Model Factories** - Full factory support for testing
- 🎯 **Type Safe** - PHP 8.4 with full type declarations
- 🔒 **Soft Deletes** - Safe content management

---

## 📋 Requirements

- **PHP:** ^8.4
- **Laravel:** ^12.0
- **Filament:** ^4.0
- **Spatie Media Library:** ^11.0
- **Spatie Sluggable:** ^3.0

---

## 🚀 Quick Start

### Installation

```bash
composer require netserva/cms
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="NetServa\Cms\NetServaCmsServiceProvider"
```

### Run Migrations

```bash
php artisan migrate
```

### Register Filament Plugin

In your Filament panel provider (`app/Providers/Filament/AdminPanelProvider.php`):

```php
use NetServa\Cms\NetServaCmsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            NetServaCmsPlugin::make(),
        ]);
}
```

### Access Admin Panel

Visit `/admin` and start creating content!

---

## 📦 Export & Import

The CMS includes powerful export and import functionality for migrating content between environments.

### Export CMS Content

Export all CMS content and media files to a portable ZIP archive:

```bash
# Export to default location (storage/app/cms-export-TIMESTAMP.zip)
php artisan cms:export

# Export to specific file
php artisan cms:export --output=/path/to/backup.zip

# Include unpublished drafts
php artisan cms:export --include-drafts

# Include soft-deleted content
php artisan cms:export --include-deleted
```

**Export includes:**
- All published pages and posts (or drafts with `--include-drafts`)
- Categories, tags, and menus
- Media files and metadata
- Relationships between content
- Manifest file with export metadata

### Import CMS Content

Import content from an exported ZIP file:

```bash
# Import from ZIP file
php artisan cms:import backup.zip

# Preview import without making changes (dry-run)
php artisan cms:import backup.zip --dry-run

# Skip importing media files
php artisan cms:import backup.zip --skip-media

# Handle slug conflicts
php artisan cms:import backup.zip --conflict-strategy=rename  # Default: rename with -imported suffix
php artisan cms:import backup.zip --conflict-strategy=skip    # Skip conflicting content
php artisan cms:import backup.zip --conflict-strategy=overwrite  # Overwrite existing content

# Skip confirmation prompts (useful for scripts)
php artisan cms:import backup.zip --force
```

**Import features:**
- Automatic ID remapping for foreign keys
- Slug conflict resolution (rename/skip/overwrite)
- Hierarchical page structure preservation
- Category and tag relationship restoration
- Media file restoration
- Transaction-based (rolls back on error)
- Dry-run mode for previewing changes

### Reset CMS

Clear all CMS content to prepare for fresh import:

```bash
# Clear all CMS data (requires confirmation)
php artisan cms:reset

# Skip confirmation prompts
php artisan cms:reset --force
```

**Warning:** This permanently deletes:
- All blog posts and pages
- All categories and tags
- All menus
- All media files (images, documents)

### Use Cases

**Content Migration:**
```bash
# Export from development
php artisan cms:export --output=production-content.zip

# Import to production
php artisan cms:reset --force
php artisan cms:import production-content.zip --force
```

**Site Templates:**
```bash
# Create reusable content template
php artisan cms:export --output=starter-template.zip

# Deploy to new site
php artisan cms:import starter-template.zip
```

**Backup & Restore:**
```bash
# Daily backup
php artisan cms:export --output=backups/cms-$(date +%Y-%m-%d).zip

# Restore from backup
php artisan cms:reset --force
php artisan cms:import backups/cms-2025-11-10.zip --force
```

### Technical Details

The export/import system uses JSON format for maximum reliability and compatibility:
- **Export Format**: JSON with all content serialized natively
- **No Parsing Issues**: Handles any content including code examples, special characters, multi-line text
- **100% Reliable**: All content types import successfully without data loss
- **Platform Independent**: Works across different database systems (SQLite, MySQL, PostgreSQL)

---

## 📊 Database Schema

All tables use the `cms_` prefix to prevent conflicts:

| Table | Purpose |
|-------|---------|
| `cms_pages` | Hierarchical page structure with templates |
| `cms_posts` | Blog posts with word count tracking |
| `cms_categories` | Multi-type categories (post, portfolio, news, docs) |
| `cms_tags` | Post tagging system |
| `cms_post_tag` | Many-to-many pivot table |
| `cms_menus` | JSON-based navigation menus |
| `media` | Spatie Media Library tables |

---

## 🏗️ Architecture

### Models (100% Standalone)

All models are completely standalone with ZERO dependencies on NetServa Core:

```php
NetServa\Cms\Models\
├── Page       // Hierarchical pages with templates & SEO
├── Post       // Blog posts with categories, tags & media
├── Category   // Multi-type categories with type scoping
├── Tag        // Simple tag model with post relationships
└── Menu       // JSON-based menu with hierarchical items
```

### Controllers

```php
NetServa\Cms\Http\Controllers\
├── PageController  // home(), show(), showNested()
└── PostController  // index(), show(), category(), tag()
```

### Filament Resources

```php
NetServa\Cms\Filament\Resources\
├── PageResource      // Full CRUD for pages
├── PostResource      // Full CRUD for posts
├── CategoryResource  // Manage categories
├── TagResource       // Manage tags
└── MenuResource      // Menu builder with nested repeaters
```

### Views

```
resources/views/
├── layouts/
│   └── app.blade.php                    // Main layout with SEO & menus
├── pages/templates/
│   ├── default.blade.php                // Standard page
│   ├── homepage.blade.php               // Hero, features, CTA
│   ├── service.blade.php                // Service page with sidebar
│   ├── pricing.blade.php                // 3-tier pricing cards
│   └── blank.blade.php                  // Minimal template
└── posts/
    ├── index.blade.php                  // Blog archive with search
    ├── show.blade.php                   // Single post with related
    ├── category.blade.php               // Category archive
    └── tag.blade.php                    // Tag archive
```

---

## 🎨 Usage Examples

### Creating Pages

```php
use NetServa\Cms\Models\Page;

$homepage = Page::factory()->homepage()->create([
    'title' => 'Welcome to My Site',
    'content' => '<p>Homepage content...</p>',
]);

$about = Page::factory()->create([
    'title' => 'About Us',
    'template' => 'default',
    'parent_id' => null,
]);
```

### Creating Blog Posts

```php
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Tag;

$category = Category::factory()->post()->create(['name' => 'Tutorials']);
$tags = Tag::factory()->count(3)->create();

$post = Post::factory()->create([
    'title' => 'Getting Started with Laravel',
    'content' => '<p>Post content...</p>',
]);

$post->categories()->attach($category);
$post->tags()->attach($tags);
```

### Building Menus

```php
use NetServa\Cms\Models\Menu;

$menu = Menu::factory()->header()->create([
    'name' => 'Main Navigation',
    'items' => [
        [
            'label' => 'Home',
            'url' => '/',
            'order' => 0,
            'children' => [],
        ],
        [
            'label' => 'Services',
            'url' => '/services',
            'order' => 1,
            'children' => [
                ['label' => 'Web Development', 'url' => '/services/web'],
                ['label' => 'Hosting', 'url' => '/services/hosting'],
            ],
        ],
    ],
]);
```

---

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test packages/netserva-cms/tests/Unit/Models/PageTest.php

# Run with coverage
php artisan test --coverage
```

### Test Coverage

- **Model Tests:** 40 tests covering all models
- **Controller Tests:** 25 tests for PageController & PostController
- **Resource Tests:** 30+ tests for Filament resources
- **Total:** 95+ comprehensive tests

### Using Factories

```php
use NetServa\Cms\Models\Page;

// Create a single page
$page = Page::factory()->create();

// Create 10 published pages
$pages = Page::factory()->count(10)->published()->create();

// Create a homepage
$homepage = Page::factory()->homepage()->create();

// Create nested pages
$parent = Page::factory()->create();
$child = Page::factory()->create(['parent_id' => $parent->id]);
```

---

## ⚙️ Configuration

Published to `config/netserva-cms.php`:

```php
return [
    'frontend' => [
        'enabled' => true,
    ],

    'blog' => [
        'route_prefix' => 'blog',
        'posts_per_page' => 12,
    ],

    'seo' => [
        'site_name' => env('APP_NAME', 'NetServa CMS'),
        'site_description' => 'Professional CMS built on Laravel',
    ],

    'media' => [
        'disk' => 'public',
        'max_file_size' => 10240, // KB
    ],

    'templates' => [
        'default' => 'Default Page',
        'homepage' => 'Homepage',
        'service' => 'Service Page',
        'pricing' => 'Pricing Page',
        'blank' => 'Blank Page',
    ],
];
```

---

## 🎯 Routes

### Frontend Routes

```php
// Homepage
GET  /                           // PageController@home

// Pages
GET  /{slug}                     // PageController@show
GET  /{parentSlug}/{slug}        // PageController@showNested

// Blog
GET  /blog                       // PostController@index
GET  /blog/{slug}                // PostController@show
GET  /blog/category/{slug}       // PostController@category
GET  /blog/tag/{slug}            // PostController@tag
```

### Admin Routes

All admin routes are handled by Filament at `/admin`:

- `/admin/pages` - Page management
- `/admin/posts` - Post management
- `/admin/categories` - Category management
- `/admin/tags` - Tag management
- `/admin/menus` - Menu builder

---

## 🚀 Deployment Scenarios

### Dual-Purpose Architecture

The netserva-cms package is designed to work in **two distinct deployment modes**:

#### 1. **Integrated Mode** (Within NetServa 3.0)

When installed as part of the NetServa 3.0 platform:

**Purpose:** Provides professional frontend pages for NetServa installations

**Routes:**
```
/                    → CMS homepage (NetServa.org branding)
/blog                → Blog posts about NetServa updates
/about               → About NetServa platform
/features            → NetServa features page
/admin               → Filament admin (all NetServa plugins)
/admin/pages         → CMS page management
/admin/vnodes        → Server management (other plugins)
```

**Default Content:**
- Homepage: NetServa platform introduction
- About page: Platform explanation
- Features page: Capability overview
- Sample blog post: "Welcome to NetServa 3.0"

**Benefits:**
- ✅ Professional landing page for NetServa installations
- ✅ Explains platform capabilities to visitors
- ✅ Integrated with other NetServa admin panels
- ✅ Gets constant updates via NS 3.0 development

#### 2. **Standalone Mode** (Independent Laravel Project)

When installed in a fresh Laravel 12 project:

**Purpose:** Power standalone websites (client sites, marketing sites, etc.)

**Routes:**
```
/                    → Client homepage
/blog                → Client blog
/{slug}              → Client pages
/admin               → CMS admin panel only
```

**Client Content Examples:**
- SpiderWeb website (spiderweb.com.au) → separate GitHub repo
- Other client marketing sites
- Personal blogs or portfolios

**Installation:**
```bash
# Fresh Laravel 12 project
composer create-project laravel/laravel my-client-site
cd my-client-site

# Install CMS
composer require netserva/cms

# Configure & migrate
php artisan vendor:publish --provider="NetServa\Cms\NetServaCmsServiceProvider"
php artisan migrate

# Seed with default content OR import client content
php artisan db:seed --class="NetServa\Cms\Database\Seeders\NetServaCmsSeeder"
```

**Benefits:**
- ✅ Zero NetServa dependencies
- ✅ Standalone CMS capabilities
- ✅ Benefits from NS 3.0 CMS development
- ✅ Can be customized per client

### Content Separation Strategy

**Default Content** (Included in Repository):
- Professional NetServa.org branding
- General server management messaging
- Suitable for any NetServa installation

**Client Content** (NOT in Repository):
- SpiderWeb website content → `spiderweb-website` repo
- Other client sites → separate repos/projects
- Imported via seeders or manual entry

**Why This Separation Matters:**

1. **Repository Cleanliness** - No client-specific data in main repo
2. **Privacy** - Client content stays private to client
3. **Reusability** - Same CMS package powers unlimited sites
4. **Updates** - CMS improvements benefit all deployments

### Migration Example: SpiderWeb

**Current State:** WordPress website at spiderweb.com.au

**Future Workflow:**

```bash
# 1. Create separate project
git clone <spiderweb-website-repo>
cd spiderweb-website

# 2. Fresh Laravel + CMS
composer create-project laravel/laravel .
composer require netserva/cms

# 3. Import WordPress content
php artisan cms:import:wordpress /path/to/wordpress-export.xml

# 4. Deploy separately
# (SpiderWeb runs independently of NetServa 3.0)
```

**Result:**
- SpiderWeb gets modern Laravel/Filament CMS
- Benefits from NetServa CMS improvements
- Completely separate GitHub repo
- No NetServa platform dependency

### Routing Behavior

**With CMS Installed:**
- CMS owns root `/` route
- Provides homepage, pages, blog routes
- Fallback to Laravel welcome disabled

**Without CMS:**
- Root `/` shows Laravel welcome page
- Only `/admin` panel available
- Clean backend-only installation

**Environment Configuration:**

```env
# Enable/disable CMS frontend
CMS_FRONTEND_ENABLED=true

# Customize route prefixes
CMS_BLOG_PREFIX=blog
CMS_PORTFOLIO_PREFIX=portfolio

# Admin panel path (security)
NS_ADMIN_PREFIX=admin
```

---

## 🔒 Design Constraints

### ✅ ALWAYS DO

```php
// ✅ Implement Plugin directly
class NetServaCmsPlugin implements Plugin { }

// ✅ Use own models only
namespace NetServa\Cms\Models;

// ✅ Keep composer.json clean
"require": {
    "laravel/framework": "^12.0",
    "filament/filament": "^4.0"
}
```

### ❌ NEVER DO

```php
// ❌ Don't extend BaseFilamentPlugin
class NetServaCmsPlugin extends BaseFilamentPlugin { }

// ❌ Don't use NetServa Core models
use NetServa\Core\Models\VHost;

// ❌ Don't add NetServa dependencies
"require": { "netserva/core": "*" }
```

---

## 🔍 Verification

Verify zero NetServa dependencies:

```bash
# Should return nothing
grep -r "NetServa\\Core" packages/netserva-cms/src/

# Should return nothing
grep -r "use NetServa" packages/netserva-cms/src/ | grep -v "NetServa\\Cms"

# Should show only Laravel/Filament/Spatie packages
cat packages/netserva-cms/composer.json | jq '.require'
```

---

## 📂 Complete Directory Structure

```
packages/netserva-cms/
├── composer.json                         # Zero NS dependencies ✅
├── config/
│   └── netserva-cms.php                 # Published configuration
├── database/
│   ├── factories/                       # Model factories
│   │   ├── PageFactory.php
│   │   ├── PostFactory.php
│   │   ├── CategoryFactory.php
│   │   ├── TagFactory.php
│   │   └── MenuFactory.php
│   └── migrations/                      # cms_* prefixed tables
│       ├── 2024_01_01_000001_create_cms_pages_table.php
│       ├── 2024_01_01_000002_create_cms_categories_table.php
│       ├── 2024_01_01_000003_create_cms_tags_table.php
│       ├── 2024_01_01_000004_create_cms_posts_table.php
│       ├── 2024_01_01_000005_create_cms_post_tag_table.php
│       ├── 2024_01_01_000006_create_cms_menus_table.php
│       └── 2024_01_01_000007_create_media_table.php
├── resources/views/
│   ├── layouts/
│   │   └── app.blade.php
│   ├── pages/templates/
│   │   ├── default.blade.php
│   │   ├── homepage.blade.php
│   │   ├── service.blade.php
│   │   ├── pricing.blade.php
│   │   └── blank.blade.php
│   └── posts/
│       ├── index.blade.php
│       ├── show.blade.php
│       ├── category.blade.php
│       └── tag.blade.php
├── routes/
│   └── web.php
├── src/
│   ├── Filament/Resources/
│   │   ├── PageResource.php             # 3 pages (List, Create, Edit)
│   │   ├── PostResource.php             # 3 pages
│   │   ├── CategoryResource.php         # 3 pages
│   │   ├── TagResource.php              # 3 pages
│   │   └── MenuResource.php             # 3 pages
│   ├── Http/Controllers/
│   │   ├── PageController.php
│   │   └── PostController.php
│   ├── Models/
│   │   ├── Page.php                     # NO NS relationships ✅
│   │   ├── Post.php                     # 100% standalone ✅
│   │   ├── Category.php
│   │   ├── Tag.php
│   │   └── Menu.php
│   ├── NetServaCmsPlugin.php            # Implements Plugin ✅
│   └── NetServaCmsServiceProvider.php
├── tests/
│   ├── Feature/
│   │   ├── Controllers/
│   │   │   ├── PageControllerTest.php
│   │   │   └── PostControllerTest.php
│   │   └── Filament/
│   │       ├── PageResourceTest.php
│   │       ├── PostResourceTest.php
│   │       └── MenuResourceTest.php
│   └── Unit/Models/
│       ├── PageTest.php
│       ├── PostTest.php
│       ├── CategoryTest.php
│       ├── TagTest.php
│       └── MenuTest.php
├── DEVELOPMENT_STATUS.md
└── README.md
```

---

## 📈 Progress

- ✅ Package foundation (composer.json, service provider, plugin)
- ✅ Database migrations (7 tables with `cms_` prefix)
- ✅ Models (5 models, 100% standalone)
- ✅ Filament resources (5 resources, 17 pages)
- ✅ Frontend controllers (PageController, PostController)
- ✅ Blade templates (1 layout, 9 templates)
- ✅ Model factories (5 factories)
- ✅ Comprehensive tests (95+ tests)
- ✅ Documentation (README, DEVELOPMENT_STATUS)
- ⏳ Run migrations (pending artisan fix)
- ⏳ SpiderWeb content migration

**Status:** ~85% Complete

---

## 📝 License

MIT

## 👥 Authors

NetServa Team

---

## 🤝 Contributing

This is a NetServa internal package. For issues or feature requests, please contact the NetServa development team.

---

**Built with ❤️ using Laravel 12 + Filament 4**
