# VConf Implementation Summary

## V-Naming Scheme

**NetServa hierarchy:** venue → vsite → vnode → vhost → **vconf** → vserv

All configuration variables follow this pattern:
- **Model:** `VConf` (not VhostEnvironmentVariable)
- **Table:** `vconfs` (not vhost_environment_variables)
- **Relationship:** `$vhost->vconfs()` (not envVariables)

## Default Values Changed

### Path Structure
Changed from `/home/u/` to `/srv/` base:

| Variable | Old Value | New Value |
|----------|-----------|-----------|
| **VPATH** | `/home/u` | `/srv` |
| **UPATH** | `/home/u/$VHOST` | `/srv/$VHOST` |
| **WPATH** | `/home/u/$VHOST/var/www/html` | `/srv/$VHOST/web` |
| **MPATH** | `/home/u/$VHOST/home` | `/srv/$VHOST/msg` |

### OS Defaults
Changed to Debian Trixie:

| Variable | Old Value | New Value |
|----------|-----------|-----------|
| **OSTYP** | `ubuntu` (detected) | `debian` |
| **OSREL** | `bookworm` or `noble` | `trixie` |
| **OSMIR** | `archive.ubuntu.com` | `deb.debian.org` |

### New Variable Added

| Variable | Value | Description |
|----------|-------|-------------|
| **VNODE** | `markc` (vnode name) | Added to all generated configs |

## Example Output

### Before (NetServa 1.0 style with /home/u/)
```bash
UPATH='/home/u/example.com'
WPATH='/home/u/example.com/var/www/html'
MPATH='/home/u/example.com/home'
OSREL='bookworm'
```

### After (NetServa 3.0 with /srv/)
```bash
MPATH='/srv/example.com/msg'
OSMIR='deb.debian.org'
OSREL='trixie'
OSTYP='debian'
UPATH='/srv/example.com'
VNODE='markc'
WPATH='/srv/example.com/web'
```

## OS Detection

### Automatic Remote Detection
When running `addvconf`, the OS is automatically detected from the remote server by reading `/etc/os-release`:

```bash
php artisan addvconf markc example.com
# 🔧 Initializing configuration for: example.com
#    Server: markc
#
# ⠂ Detecting OS from /etc/os-release...
#    Detected OS: debian trixie
#    Mode: Full (53+ environment variables)
```

### Detected Variables
The following variables are set based on remote OS detection:

| Variable | Description | Example |
|----------|-------------|---------|
| **OSTYP** | OS distribution ID | `debian`, `ubuntu`, `alpine`, `cachyos` |
| **OSREL** | OS version codename | `trixie`, `bookworm`, `noble` |
| **OSMIR** | OS package mirror | `deb.debian.org`, `archive.ubuntu.com` |

### OS-Specific Overrides
Different OS types receive specialized configuration:

- **Debian**: PHP 8.2, standard paths
- **Alpine**: PHP 84 (no dots), `/etc/pdns`, `nginx` user
- **CachyOS/Arch**: PHP 8.4, `/etc/php`, `http` user
- **Ubuntu**: PHP 8.3, standard paths

### Fallback Behavior
If OS detection fails (server unreachable, no `/etc/os-release`), defaults to Debian Trixie:
```
⚠️  Could not detect OS, using defaults
OSTYP='debian'
OSREL='trixie'
OSMIR='deb.debian.org'
```

## Usage

### Generate configuration for a vhost
```bash
# Full configuration (54 variables with OS detection)
php artisan addvconf markc example.com

# Minimal configuration (13 essential variables)
php artisan addvconf markc example.com --minimal
```

### View configuration
```bash
# Plain sorted bash-sourceable format (DEFAULT)
php artisan shvconf markc example.com

# Source into shell
source <(php artisan shvconf markc example.com)
echo $WPATH  # /srv/example.com/web

# Table format
php artisan shvconf markc example.com --table

# JSON format
php artisan shvconf markc example.com --json
```

## Database Structure

### Dedicated `vconfs` Table

```sql
CREATE TABLE vconfs (
    id BIGINT PRIMARY KEY,
    fleet_vhost_id BIGINT,
    name VARCHAR(5),              -- 5-char uppercase: WPATH, U_UID, etc.
    value TEXT,
    category VARCHAR(20),         -- Auto-categorized: paths, database, etc.
    is_sensitive BOOLEAN,         -- Auto-detected for *PASS variables

    UNIQUE(fleet_vhost_id, name)
);
```

### Model Usage

```php
use NetServa\Cli\Models\VConf;

// Get all vconfs for a vhost
$vhost->vconfs;

// Get paths only
$vhost->vconfs()->where('category', 'paths')->get();

// Get passwords
$vhost->vconfs()->where('is_sensitive', true)->get();

// Get/Set single variable
$wpath = $vhost->getEnvVar('WPATH');
$vhost->setEnvVar('WPATH', '/srv/example.com/web');

// Bulk set variables
$vhost->setEnvVars([
    'WPATH' => '/srv/example.com/web',
    'UPATH' => '/srv/example.com',
    'MPATH' => '/srv/example.com/msg',
]);
```

## Variable Naming Rules

All variables MUST be:
- **5 characters maximum**
- **Uppercase letters** (A-Z)
- **Optional underscore** (_)

Examples:
- ✅ `WPATH` (5 chars)
- ✅ `U_UID` (5 chars with underscore)
- ✅ `VNODE` (5 chars)
- ❌ `WEBPATH` (7 chars - too long)
- ❌ `wpath` (lowercase - invalid)

Validation enforced at model level:
```php
VConf::validateName('WPATH');  // true
VConf::validateName('WEBPATH');  // false
```

## Auto-Categorization

Variables are automatically categorized:

| Category | Detection Rule | Examples |
|----------|----------------|----------|
| **paths** | Ends with `PATH` | WPATH, UPATH, MPATH |
| **passwords** | Ends with `PASS` | DPASS, UPASS, WPASS |
| **user** | Starts with `U_` or `A_` | U_UID, U_GID, A_UID |
| **database** | Starts with `D` | DNAME, DPASS, DTYPE |
| **web** | Specific names | V_PHP, WUGID, WPUSR |
| **os** | Specific names | OSTYP, OSREL, OSMIR |
| **domain** | Specific names | VHOST, VNODE, HDOMN |

## Filament Integration

The `vconfs` table is perfect for Filament CRUD:

```php
// List all vconfs for a vhost with categories
VConfResource::table()
    ->columns([
        TextColumn::make('name'),
        TextColumn::make('value')->toggleable(),
        TextColumn::make('category')->badge(),
        IconColumn::make('is_sensitive')->boolean(),
    ])
    ->filters([
        SelectFilter::make('category'),
        Filter::make('sensitive')->query(fn($q) => $q->where('is_sensitive', true)),
    ]);
```

## Migration Path

### From JSON column (legacy)
```php
// Existing vhosts still have environment_vars JSON column
$vars = $vhost->environment_vars;  // ['WPATH' => '/srv/...', ...]

// Migrate to vconfs table
$vhost->setEnvVars($vars);  // Writes to both vconfs table + JSON

// Read operations check vconfs first, fall back to JSON
$wpath = $vhost->getEnvVar('WPATH');  // Prefers vconfs table
```

### Dual storage for compatibility
Both storage methods maintained:
1. **Primary:** `vconfs` table (queryable, indexed, Filament-ready)
2. **Backup:** `fleet_vhosts.environment_vars` JSON column (backward compatibility)

All write operations update both locations automatically.

## Files Changed

1. **Migration:** `packages/netserva-cli/database/migrations/2025_10_07_095642_create_vconfs_table.php`
2. **Model:** `packages/netserva-cli/src/Models/VConf.php`
3. **Generator:** `packages/netserva-cli/src/Services/NetServaEnvironmentGenerator.php`
4. **FleetVHost:** `packages/netserva-fleet/src/Models/FleetVHost.php`
5. **Commands:** `packages/netserva-cli/src/Console/Commands/ShvconfCommand.php`

## Testing

```bash
# Test generation
php artisan tinker
>>> $vhost = FleetVHost::first();
>>> $gen = app(\NetServa\Cli\Services\NetServaEnvironmentGenerator::class);
>>> $vars = $gen->generate($vhost->vnode, 'test.example.com');
>>> $vars['VPATH']  // "/srv"
>>> $vars['WPATH']  // "/srv/test.example.com/web"
>>> $vars['OSREL']  // "trixie"
```

## Documentation

- **Architecture:** `ARCHITECTURE.md` - Overall plugin design
- **Environment Generation:** `ENVIRONMENT_GENERATION.md` - Variable generation details
- **This File:** `VCONF_SUMMARY.md` - V-naming and default changes
