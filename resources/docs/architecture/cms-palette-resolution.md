# CMS Frontend Palette Resolution Architecture

**Date:** 2025-11-13
**Status:** ✅ Implemented
**Related:** `PaletteResolver.php`, `ThemeService.php`, `SetFleetContext.php`

---

## The Challenge

**Problem:** CMS frontend visitors are anonymous (not authenticated). How do they get palette colors?

**User Question:** "How does the CMS frontend know which user/palette to use?"

**Answer:** CMS frontend visitors DON'T use a user's palette. Instead, they use **fleet-based palette resolution**.

---

## The Elegant Solution

NetServa's Fleet hierarchy already supports palette inheritance. The missing piece was **automatically setting fleet context based on the domain being visited**.

### Architecture Flow

```
1. Visitor hits example.com
2. SetFleetContext middleware intercepts request
3. Looks up FleetVhost by domain (example.com)
4. Sets fleet context: ['type' => 'vhost', 'id' => 123]
5. PaletteResolver traverses fleet hierarchy:
   - vhost palette (if set)
   - → vnode palette (if vhost doesn't have one)
   - → vsite palette (if vnode doesn't have one)
   - → venue palette (if vsite doesn't have one)
6. Falls back to cms.frontend_palette_id setting (site-wide default)
7. Finally falls back to system default (slate)
```

---

## Complete Palette Resolution Hierarchy

**For ALL users (admin and frontend):**

1. **Session Override** (highest priority)
   - Temporary palette preview/demo
   - Set via: `PaletteResolver::setOverride($paletteId)`
   - Use case: Admin testing palette before applying

2. **Fleet Context**
   - Per-vhost, per-vnode, per-vsite, or per-venue
   - **CMS Frontend:** Set automatically by `SetFleetContext` middleware based on domain
   - **Admin Panel:** Set manually when viewing Fleet resources
   - Hierarchy: vhost → vnode → vsite → venue

3. **User Preference**
   - Authenticated admin users only
   - Stored in `users.palette_id`
   - Changed via profile page palette selector

4. **Frontend Setting**
   - Site-wide default for anonymous visitors
   - Stored in `settings` table as `cms.frontend_palette_id`
   - Set via: `Setting::setValue('cms.frontend_palette_id', $paletteId)`

5. **System Default** (lowest priority)
   - Fallback: slate palette
   - Always available via `Palette::default()`

---

## Implementation Details

### 1. SetFleetContext Middleware

**Location:** `packages/netserva-cms/src/Http/Middleware/SetFleetContext.php`

**Purpose:** Automatically set fleet context for CMS frontend visitors based on domain

**How it Works:**
```php
// Extract domain from request
$domain = $request->getHost(); // e.g., "example.com"

// Find vhost by domain
$vhost = FleetVhost::where('domain', $domain)->first();

if ($vhost) {
    // Set fleet context for palette resolution
    $resolver = app(PaletteResolver::class);
    $resolver->setContext('vhost', $vhost->id);
}
```

**Registration:**
- Automatically registered in `NetServaCmsServiceProvider::registerFleetContextMiddleware()`
- Added to `web` middleware group
- Only registers if Fleet models are available (optional integration)

### 2. PaletteResolver Service

**Location:** `app/Services/PaletteResolver.php`

**Modified:** Added step 4 (frontend setting) in hierarchy

```php
// 4. Site-wide frontend palette (for public visitors)
if (class_exists(\NetServa\Core\Models\Setting::class)) {
    try {
        $frontendPaletteId = \NetServa\Core\Models\Setting::getValue('cms.frontend_palette_id');
        if ($frontendPaletteId) {
            $palette = Palette::find($frontendPaletteId);
            if ($palette) {
                return $palette;
            }
        }
    } catch (\Exception $e) {
        // Silently continue to default if settings unavailable
    }
}
```

### 3. ThemeService CSS Generation

**Location:** `packages/netserva-cms/src/Services/ThemeService.php`

**Modified:** Cache key now includes palette ID to regenerate CSS when palette changes

```php
public function generateCssVariables(): string
{
    $theme = $this->getActive();

    // Include palette ID in cache key so CSS regenerates when palette changes
    try {
        $paletteResolver = app(\App\Services\PaletteResolver::class);
        $palette = $paletteResolver->getCurrentPalette();
        $cacheKey = "cms.theme.{$theme->id}.palette.{$palette->id}.css";
    } catch (\Exception $e) {
        $cacheKey = "cms.theme.{$theme->id}.css";
    }

    return Cache::remember(
        $cacheKey,
        $this->cacheTtl,
        fn () => $this->buildCssVariables($theme)
    );
}
```

---

## Configuration Guide

### Per-VHost Palettes (Recommended)

Configure palettes via Fleet resources in admin panel:

```php
// Option 1: Via Filament admin panel
// 1. Go to Fleet → VHosts
// 2. Edit vhost (e.g., example.com)
// 3. Select palette from dropdown
// 4. Save

// Option 2: Via Tinker
$vhost = FleetVhost::where('domain', 'example.com')->first();
$vhost->palette_id = 3; // Ocean palette
$vhost->save();
```

### Inherited Palettes

Set palette at any level - it inherits down the hierarchy:

```php
// Set at venue level (all vsites, vnodes, vhosts inherit)
$venue = FleetVenue::find(1);
$venue->palette_id = 2; // Forest palette
$venue->save();

// Set at vsite level (all vnodes and vhosts in this site inherit)
$vsite = FleetVsite::find(1);
$vsite->palette_id = 4; // Sunset palette
$vsite->save();

// Set at vnode level (all vhosts on this node inherit)
$vnode = FleetVnode::find(1);
$vnode->palette_id = 5; // Midnight palette
$vnode->save();

// Set at vhost level (overrides all inherited palettes)
$vhost = FleetVhost::find(1);
$vhost->palette_id = 6; // Custom palette
$vhost->save();
```

### Site-Wide Frontend Palette (Fallback)

Set a default palette for visitors when no fleet palette is configured:

```php
use NetServa\Core\Models\Setting;

// Set frontend palette
Setting::setValue('cms.frontend_palette_id', 3, 'cms');

// Get current frontend palette
$paletteId = Setting::getValue('cms.frontend_palette_id');
```

---

## Usage Examples

### Example 1: Multi-Tenant SaaS

**Scenario:** Different customers get different brand colors

```php
// Customer A - Blue branding
$customerA = FleetVenue::create(['name' => 'Customer A']);
$customerA->palette_id = 1; // Blue palette
$customerA->save();

// Customer B - Red branding
$customerB = FleetVenue::create(['name' => 'Customer B']);
$customerB->palette_id = 2; // Red palette
$customerB->save();

// All vhosts under Customer A automatically get blue colors
// All vhosts under Customer B automatically get red colors
```

### Example 2: Development/Staging/Production

**Scenario:** Visual distinction between environments

```php
// Production - Professional slate
$prod = FleetVsite::where('name', 'Production')->first();
$prod->palette_id = 1; // Slate (professional)
$prod->save();

// Staging - Cautionary orange
$staging = FleetVsite::where('name', 'Staging')->first();
$staging->palette_id = 7; // Orange (caution)
$staging->save();

// Development - Creative purple
$dev = FleetVsite::where('name', 'Development')->first();
$dev->palette_id = 8; // Purple (creative)
$dev->save();
```

### Example 3: Geographic Regions

**Scenario:** Regional branding

```php
// US sites - Red/white/blue
$us = FleetVenue::where('region', 'US')->first();
$us->palette_id = 3; // Blue palette
$us->save();

// EU sites - Blue/gold
$eu = FleetVenue::where('region', 'EU')->first();
$eu->palette_id = 4; // Gold palette
$eu->save();
```

---

## Testing

### Test Fleet Context Resolution

```php
// Create test data
$venue = FleetVenue::factory()->create(['palette_id' => 1]);
$vsite = FleetVsite::factory()->create(['venue_id' => $venue->id]);
$vnode = FleetVnode::factory()->create(['vsite_id' => $vsite->id]);
$vhost = FleetVhost::factory()->create([
    'vnode_id' => $vnode->id,
    'domain' => 'test.example.com',
]);

// Simulate request to test.example.com
$response = $this->get('http://test.example.com');

// Verify fleet context was set
expect(session('fleet_context'))->toBe([
    'type' => 'vhost',
    'id' => $vhost->id,
]);

// Verify palette resolution
$resolver = app(PaletteResolver::class);
$palette = $resolver->getCurrentPalette();
expect($palette->id)->toBe(1); // Inherited from venue
```

### Test Hierarchy Inheritance

```php
it('inherits palette from vnode when vhost has none', function () {
    $vnode = FleetVnode::factory()->create(['palette_id' => 2]);
    $vhost = FleetVhost::factory()->create([
        'vnode_id' => $vnode->id,
        'palette_id' => null, // No palette
    ]);

    $resolver = app(PaletteResolver::class);
    $resolver->setContext('vhost', $vhost->id);

    $palette = $resolver->getCurrentPalette();
    expect($palette->id)->toBe(2); // From vnode
});
```

---

## Benefits

### ✅ Architectural Elegance
- Uses existing Fleet hierarchy (no new concepts)
- Consistent with NetServa's infrastructure-first design
- Scales to multi-tenant scenarios naturally

### ✅ Flexibility
- Configure at any level: venue, site, node, or host
- Inherit palettes down the hierarchy
- Override at any level when needed

### ✅ No Ambiguity
- Fleet context is determined by domain (infrastructure)
- No confusion about "which user's palette" for anonymous visitors
- Clear separation: admin users have preferences, infrastructure has configuration

### ✅ Graceful Degradation
- Falls back through 5 levels of hierarchy
- Always has a default (system palette)
- Works even if Fleet system unavailable

### ✅ Cache Efficiency
- CSS cached per palette
- Regenerates automatically when palette changes
- No manual cache clearing needed

---

## Related Files

- `app/Services/PaletteResolver.php` - Central palette resolution service
- `packages/netserva-cms/src/Http/Middleware/SetFleetContext.php` - Sets fleet context from domain
- `packages/netserva-cms/src/Services/ThemeService.php` - Generates CSS with palette colors
- `packages/netserva-cms/src/NetServaCmsServiceProvider.php` - Registers middleware
- `app/Providers/Filament/AdminPanelProvider.php` - Admin panel color registration

---

## Decision Log

**Why Fleet-Based (Not User-Based)?**
- Anonymous visitors don't have users
- Infrastructure-based is clearer than "which admin's preference?"
- Aligns with NetServa's Fleet hierarchy
- Scales to multi-tenant naturally

**Why Middleware (Not Service)?**
- Runs early in request lifecycle
- Sets context before any views render
- Clean separation of concerns
- Optional (only registers if Fleet available)

**Why Include Palette ID in Cache Key?**
- Different palettes need different CSS
- Automatic cache invalidation when palette changes
- No manual cache clearing needed
- Prevents stale CSS from being served

---

**Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** Claude Code + Mark
**Status:** Production-ready ✅
