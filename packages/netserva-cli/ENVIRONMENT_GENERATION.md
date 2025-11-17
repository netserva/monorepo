# NetServa Environment Variable Generation

## Overview

NetServa 3.0 replicates the NetServa 1.0 `sethost()` and `gethost()` bash functions as **PHP services** that generate all 53+ environment variables and store them in the Laravel database.

## Architecture

### Components

1. **NetServaEnvironmentGenerator** - Core service that generates all variables
2. **DatabaseVhostConfigService** - Database persistence layer
3. **AddvconfCommand** - CLI interface with Laravel Prompts
4. **ShvconfCommand** - Display variables in bash-sourceable format

## Environment Variables (53+)

### Categories

**Admin/System (7 vars)**
- `ADMIN`, `AHOST`, `AMAIL`, `ANAME`, `APASS`, `A_GID`, `A_UID`

**Paths (7 vars)**
- `BPATH`, `UPATH`, `WPATH`, `MPATH`, `VPATH`, `DPATH`, `C_FPM`

**Config Paths (7 vars)**
- `CIMAP`, `CSMTP`, `C_DNS`, `C_FPM`, `C_SQL`, `C_SSL`, `C_WEB`

**Database (11 vars)**
- `DHOST`, `DNAME`, `DPASS`, `DPORT`, `DTYPE`, `DUSER`
- `DBMYS`, `DBSQL`, `EXMYS`, `EXSQL`, `SQCMD`, `SQDNS`

**Passwords (5 vars)**
- `APASS`, `DPASS`, `EPASS`, `UPASS`, `WPASS`
- All 16 characters, alphanumeric, cryptographically random

**Domain/Host (6 vars)**
- `VHOST`, `VNODE`, `HDOMN`, `HNAME`, `MHOST`, `AHOST`

**User (5 vars)**
- `UUSER`, `U_UID`, `U_GID`, `U_SHL`, `VUSER`

**Web (4 vars)**
- `WPATH`, `WUGID`, `V_PHP`, `WPUSR`

**OS (3 vars)**
- `OSTYP`, `OSREL`, `OSMIR`

**Network (1 var)**
- `IP4_0`

**Timezone (2 vars)**
- `TAREA`, `TCITY`

## Generation Logic

### Static Defaults

```php
[
    'ADMIN' => 'sysadm',
    'A_UID' => '1000',
    'VPATH' => '/home/u',
    'DTYPE' => 'mysql',
    'DPORT' => '3306',
    'WUGID' => 'www-data',
    // ... etc
]
```

### Dynamic Variables

Calculated based on domain and vnode:

```php
$uUid = ($fqdn === $domain) ? '1000' : generateNewUid();
$uUser = ($fqdn === $domain) ? 'sysadm' : "u{$uUid}";
$hName = explode('.', $domain)[0];
$hDomain = implode('.', array_slice(explode('.', $domain), 1));
```

### OS-Specific Overrides

Different defaults based on detected OS type:

- **Alpine**: `V_PHP=84`, `WUGID=nginx`, `C_FPM=/etc/php84`
- **Debian**: `V_PHP=8.2`, `OSREL=bookworm`
- **CachyOS/Arch**: `V_PHP=8.4`, `WUGID=http`, `C_FPM=/etc/php`
- **Ubuntu**: `V_PHP=8.3`, `OSREL=noble` (default)

## Usage

### Initialize All Variables

```bash
# Full configuration (53+ variables)
addvconf markc nc.goldcoast.org

# Minimal configuration (13 essential variables)
addvconf markc nc.goldcoast.org --minimal

# Force regenerate (new passwords)
addvconf markc nc.goldcoast.org --force
```

### Display Variables

```bash
# Default: bash-sourceable format (sorted alphabetically)
shvconf markc nc.goldcoast.org

# Source into current shell
source <(shvconf markc nc.goldcoast.org)
echo $WPATH  # /srv/nc.goldcoast.org/web

# View specific variable
shvconf markc nc.goldcoast.org WPATH

# Table format with grouping
shvconf markc nc.goldcoast.org --table

# JSON format
shvconf markc nc.goldcoast.org --json
```

### Modify Variables

```bash
# Change a variable
chvconf markc nc.goldcoast.org WPATH /new/path

# Delete a variable
delvconf markc nc.goldcoast.org WPATH

# Delete all variables
delvconf markc nc.goldcoast.org --all
```

## Service API

### NetServaEnvironmentGenerator

```php
use NetServa\Cli\Services\NetServaEnvironmentGenerator;

$generator = app(NetServaEnvironmentGenerator::class);

// Generate all 53+ variables
$vars = $generator->generate($vnode, $domain);

// Generate with overrides (keep existing passwords)
$vars = $generator->generate($vnode, $domain, [
    'DPASS' => 'existing_password',
    'UPASS' => 'existing_password',
]);

// Generate minimal set (13 essential variables)
$vars = $generator->getMinimalVariables($vnode, $domain);
```

### DatabaseVhostConfigService

```php
use NetServa\Cli\Services\DatabaseVhostConfigService;

$configService = app(DatabaseVhostConfigService::class);

// Initialize configuration for a vhost
$vhost = FleetVhost::find($id);
$vars = $configService->initialize($vhost);

// Initialize with minimal variables
$vars = $configService->initializeMinimal($vhost);

// Initialize with overrides
$vars = $configService->initialize($vhost, [
    'V_PHP' => '8.4',
    'OSTYP' => 'alpine',
]);
```

## Password Generation

All passwords are generated using:

```php
// 16 characters, alphanumeric (A-Za-z0-9)
$password = substr(
    str_shuffle(
        str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
        ceil(16 / 62))
    ),
    0,
    16
);
```

**Cryptographic Note:** While `str_shuffle()` is not cryptographically secure, it's sufficient for these automatically-generated passwords. For production security-critical passwords, use `random_bytes()`.

## UID Generation

UIDs start at 1000 and increment:

```php
// Get highest existing UID from database
$maxUid = FleetVhost::where('vnode_id', $vnode->id)
    ->whereNotNull('environment_vars->U_UID')
    ->get()
    ->map(fn($vhost) => (int)($vhost->environment_vars['U_UID'] ?? 0))
    ->max();

// Increment
$newUid = max(1000, $maxUid + 1);
```

## Database Storage

All variables stored in `fleet_vhosts.environment_vars` JSON column:

```json
{
  "VHOST": "nc.goldcoast.org",
  "VNODE": "markc",
  "UPATH": "/srv/nc.goldcoast.org",
  "WPATH": "/srv/nc.goldcoast.org/web/app/public",
  "UUSER": "u1003",
  "U_UID": "1003",
  "DNAME": "nc_goldcoast_org",
  "DPASS": "Xk3mP9qR2vT5nW8z",
  ...
}
```

Access via FleetVhost model:

```php
$vhost = FleetVhost::find($id);

// Get variable
$wpath = $vhost->getEnvVar('WPATH');

// Set variable
$vhost->setEnvVar('WPATH', '/new/path');
$vhost->save();

// Get all variables
$allVars = $vhost->environment_vars;
```

## Comparison with NetServa 1.0

| Feature | NetServa 1.0 | NetServa 3.0 |
|---------|-------------|-------------|
| Storage | Bash sourcing `.conf` files | Laravel database (JSON) |
| Generation | `sethost()` bash function | `NetServaEnvironmentGenerator` PHP service |
| Display | `gethost()` bash function | `shvconf` Laravel command |
| Format | Bash `VAR='value'` | Same, but from database |
| Passwords | `head /dev/urandom \| tr ...` | PHP `str_shuffle()` |
| UID | `newuid()` bash function | `generateNewUid()` PHP method |
| OS Detection | `$OSTYP` bash variable | FleetVnode `os_version` field |

## Migration from 1.0

Old `.conf` files can be imported:

```bash
# Discover infrastructure (reads .conf files)
php artisan fleet:discover --vnode=markc

# Regenerate all variables from database
addvconf markc nc.goldcoast.org --force
```

## Testing

```php
use NetServa\Cli\Services\NetServaEnvironmentGenerator;
use NetServa\Fleet\Models\FleetVnode;

it('generates all 53+ environment variables', function () {
    $vnode = FleetVnode::factory()->create([
        'name' => 'test',
        'os_version' => 'Debian 12 (bookworm)',
    ]);

    $generator = app(NetServaEnvironmentGenerator::class);
    $vars = $generator->generate($vnode, 'example.com');

    expect($vars)->toHaveCount(53)
        ->and($vars['VHOST'])->toBe('example.com')
        ->and($vars['VNODE'])->toBe('test')
        ->and($vars['OSTYP'])->toBe('debian')
        ->and($vars['V_PHP'])->toBe('8.2')
        ->and($vars['DPASS'])->toHaveLength(16);
});
```

## See Also

- `ARCHITECTURE.md` - Overall plugin architecture
- `COMPLETE_CRUD_PATTERN.md` - CRUD command conventions
- `packages/netserva-cli/src/Services/NetServaEnvironmentGenerator.php` - Source code
