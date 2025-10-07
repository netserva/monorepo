# NetServa 3.0 Coding Style Guide

**Complete development standards and patterns for NetServa 3.0 Platform**

---

## 📋 Table of Contents

1. [Platform Schema Hierarchy](#platform-schema-hierarchy)
2. [Plugin Architecture](#plugin-architecture)
3. [Remote SSH Execution](#remote-ssh-execution)
4. [Database-First Architecture](#database-first-architecture)
5. [CLI Command Conventions](#cli-command-conventions)
6. [Testing Standards](#testing-standards)
7. [Security & Best Practices](#security--best-practices)
8. [Development Guidelines](#development-guidelines)
9. [Shell Enhancement System](#shell-enhancement-system)
10. [Development Environment](#development-environment)

---

## Platform Schema Hierarchy

**6-Layer Infrastructure Model:**

```
venue → vsite → vnode → vhost + vconf → vserv
```

### Layer Definitions

1. **venue** - Physical location or datacenter (e.g., `home-lab`, `sydney-dc`)
2. **vsite** - Logical grouping within venue (e.g., `production`, `staging`)
3. **vnode** - Virtual/physical server (e.g., `markc` at 192.168.1.227)
4. **vhost** - Virtual hosting domain (e.g., `markc.goldcoast.org`)
5. **vconf** - Configuration variables (54+ vars in `vconfs` table)
6. **vserv** - Services per vhost (nginx, php-fpm, postfix, dovecot)

---

## Plugin Architecture

### 11 NetServa Plugins

- **netserva-core** - Foundation models, services, database migrations
- **netserva-cli** - Command-line tools, bash wrappers
- **netserva-config** - Configuration management, templates
- **netserva-cron** - Scheduled tasks, automation
- **netserva-dns** - DNS management, PowerDNS integration
- **netserva-fleet** - Multi-server infrastructure
- **netserva-ipam** - IP address management
- **netserva-mail** - Email (Postfix/Dovecot/Rspamd)
- **netserva-ops** - Operational tools, administration
- **netserva-web** - Web servers (Nginx/PHP-FPM)
- **netserva-wg** - WireGuard VPN

### Standard Plugin Structure

**Service Providers:** Extend `BaseNsServiceProvider` for auto-migration loading, asset management, command registration

**Plugin Interface:** All plugins implement `NsPluginInterface` with standardized methods (getId, register, boot, enable/disable)

**Auto-Discovery:** AdminPanelProvider automatically discovers and registers all plugin resources, pages, widgets

### Key Patterns

**CLI + Web Integration:** Services work in both CLI commands and Filament resources
**Laravel Prompts:** Beautiful interactive CLI with progress bars and confirmations
**Queue Support:** Long-running operations use Laravel queues
**SSH via phpseclib:** 50ms overhead accepted for certificate-free simplicity

---

## Remote SSH Execution

**CRITICAL: ALL remote bash scripts MUST use the heredoc-based `executeScript()` method.**

### Recommended Method: executeScript()

```php
use NetServa\Cli\Services\RemoteExecutionService;

// Inject the service
public function __construct(protected RemoteExecutionService $remoteExecution) {}

// Execute complex bash script
$result = $this->remoteExecution->executeScript(
    host: 'markc',
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Arguments from caller
        domain=$1
        web_path=$2
        user=$3

        # Remote logic - proper quoting handled automatically
        if [ ! -d "$web_path" ]; then
            mkdir -p "$web_path"
            chown "$user:www-data" "$web_path"
        fi

        # Complex commands work naturally
        find "$web_path" -type d -exec chmod 755 {} \;
        find "$web_path" -type f -exec chmod 644 {} \;

        echo "Success: $domain configured"
        BASH,
    args: ['markc.goldcoast.org', '/srv/markc.goldcoast.org/web', 'u1001'],
    asRoot: true
);

// Check result
if ($result['success']) {
    echo "✅ {$result['output']}";
} else {
    echo "❌ {$result['error']}";
}
```

### With FleetVHost Environment Variables

```php
// Automatically injects all environment_vars from database
$result = $this->remoteExecution->executeScriptWithVhost(
    host: 'markc',
    vhost: $vhost,  // FleetVHost model
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Environment vars automatically available: $UPATH, $WPATH, $DPASS, etc.
        cd "$WPATH"

        # Your logic here
        echo "Web root: $WPATH"
        echo "User: $UUSER"
        BASH,
    asRoot: true
);
```

### Benefits

**✅ Reliability:**
- Proper quoting handled by heredoc
- No shell injection vulnerabilities
- Exit codes preserved correctly
- `set -euo pipefail` auto-added for safety

**✅ Maintainability:**
- Scripts readable like normal bash
- Syntax highlighting works in editors
- Easy to debug and test locally
- Clean separation of local vs remote variables

**✅ Flexibility:**
- Supports any complexity: loops, pipes, functions
- Can include inline heredocs for config files
- Arguments passed cleanly as $1, $2, etc.

### Anti-Patterns (DO NOT USE)

```php
// ❌ WRONG - String concatenation with quotes
$command = "cd {$path} && chown {$user} *";
$this->remoteExecution->executeAsRoot($host, $command);

// ❌ WRONG - escapeshellarg on entire command
$command = escapeshellarg("find {$path} -type f");

// ❌ WRONG - Multiple separate exec() calls
$this->remoteExecution->exec($host, 'mkdir /tmp/foo');
$this->remoteExecution->exec($host, 'chown user:group /tmp/foo');

// ✅ CORRECT - Single heredoc script
$this->remoteExecution->executeScript($host, <<<'BASH'
    mkdir /tmp/foo
    chown user:group /tmp/foo
    chmod 755 /tmp/foo
    BASH
);
```

### Reference Documentation

See `docs/SSH_EXECUTION_ARCHITECTURE.md` for complete implementation examples.

---

## Database-First Architecture

**CRITICAL: ALL VHost configuration, credentials, and environment variables are stored in the Laravel database.**

### vconfs Table - Configuration Storage

**Each environment variable is a separate database row:**

```sql
CREATE TABLE vconfs (
    id BIGINT,
    fleet_vhost_id BIGINT,           -- Links to fleet_vhosts
    name VARCHAR(5),                  -- 5-char variable (WPATH, DPASS, etc.)
    value TEXT,                       -- Variable value
    category VARCHAR(20),             -- Group: paths, credentials, settings
    is_sensitive BOOLEAN,             -- Password masking
    UNIQUE(fleet_vhost_id, name)
);
```

### FleetVHost Model - VConf Access

```php
// Get VHost from database
$vhost = FleetVHost::where('domain', 'markc.goldcoast.org')
    ->whereHas('vnode', fn($q) => $q->where('name', 'markc'))
    ->first();

// Access vconf variables (from vconfs table)
$upath = $vhost->vconf('UPATH');     // /srv/markc.goldcoast.org
$wpath = $vhost->vconf('WPATH');     // /srv/markc.goldcoast.org/web
$dpass = $vhost->vconf('DPASS');     // database password (masked if is_sensitive)

// Get all vconfs for a vhost
$allVconfs = $vhost->vconfs()->get();  // Collection of VConf models
```

### Discovery Process

**Before using any vhost in commands, it must exist in database:**

```bash
# 1. Discover infrastructure from remote servers
php artisan fleet:discover --vnode=markc

# This populates:
# - fleet_vnodes (servers/VMs)
# - fleet_vhosts (domains/instances)
# - vconfs (54+ configuration variables per vhost)

# 2. Now commands work
php artisan chperms markc markc.goldcoast.org
php artisan chvhost markc markc.goldcoast.org --php-version=8.4
```

### Command Implementation Pattern

```php
// Database-first approach - get VHost from database
$vhost = FleetVHost::where('domain', $vhost)
    ->whereHas('vnode', fn($q) => $q->where('name', $vnode))
    ->firstOrFail();

// Access vconf variables (from vconfs table)
$upath = $vhost->vconf('UPATH');     // /srv/markc.goldcoast.org
$wpath = $vhost->vconf('WPATH');     // /srv/markc.goldcoast.org/web
$dpass = $vhost->vconf('DPASS');     // Password (masked if sensitive)
```

### VConf Variables (54+ per vhost)

**Each variable stored as separate row in `vconfs` table:**

```php
// Example vconfs for markc.goldcoast.org:
VConf::create([
    'fleet_vhost_id' => 1,
    'name' => 'VHOST',
    'value' => 'markc.goldcoast.org',
    'category' => 'core',
]);

VConf::create([
    'fleet_vhost_id' => 1,
    'name' => 'WPATH',
    'value' => '/srv/markc.goldcoast.org/web',
    'category' => 'paths',
]);

VConf::create([
    'fleet_vhost_id' => 1,
    'name' => 'DPASS',
    'value' => 'xxxxxxxxxxxx',
    'category' => 'credentials',
    'is_sensitive' => true,
]);

// ... (51 more variables)
```

**Access via VHost model:**
```php
$vhost->vconf('DPASS')                          // Get value
$vhost->setVconf('DPASS', 'newpw', true)       // Set value (is_sensitive=true)
$vhost->vconfs()->where('category', 'paths')   // Query by category
```

---

## CLI Command Conventions

**CRITICAL: ALL NetServa CRUD commands use positional arguments in this exact order:**

```bash
<command> <vnode> <vhost> [options]
```

**VNODE:** SSH host identifier (server/VNode name)
**VHOST:** Domain name (virtual host)

### Core CRUD Commands

```bash
# VHost Management
addvhost <vnode> <vhost>              # Create virtual host
chvhost <vnode> <vhost> [--options]   # Update virtual host
delvhost <vnode> <vhost>              # Delete virtual host
shvhost [vnode] [vhost]               # Show virtual host(s)
chperms <vnode> <vhost>               # Fix permissions

# VHost Configuration (54 Environment Variables)
shvconf <vnode> <vhost> [variable]    # Show configuration variables
addvconf <vnode> <vhost>              # Add/initialize configuration
chvconf <vnode> <vhost> <var> [val]   # Change configuration variable
delvconf <vnode> <vhost> [variable]   # Delete configuration variable(s)
```

### Command Examples

```bash
# VHost Management
addvhost markc markc.goldcoast.org              # Create new vhost
chvhost markc markc.goldcoast.org --php=8.4     # Update vhost settings
delvhost markc markc.goldcoast.org              # Delete vhost
shvhost markc markc.goldcoast.org               # Show specific vhost
shvhost markc --list                            # Show all vhosts on vnode
shvhost                                         # Show all vhosts everywhere
chperms markc markc.goldcoast.org               # Fix permissions

# VHost Configuration Variables
shvconf markc markc.goldcoast.org               # Show all (NetServa 1.0 format)
shvconf markc markc.goldcoast.org WPATH         # Show specific variable value
shvconf markc markc.goldcoast.org --table       # Formatted table with groups
shvconf markc markc.goldcoast.org --json        # JSON output

addvconf markc markc.goldcoast.org              # Initialize with defaults
addvconf markc markc.goldcoast.org --minimal    # Minimal variables only
addvconf markc markc.goldcoast.org --template=wordpress  # WordPress template
addvconf markc markc.goldcoast.org --interactive # Interactive setup

chvconf markc markc.goldcoast.org WPATH /srv/markc/web   # Set variable
chvconf markc markc.goldcoast.org DPASS          # Prompt for value
chvconf markc markc.goldcoast.org WPATH --unset  # Remove variable

delvconf markc markc.goldcoast.org WPATH         # Delete one variable
delvconf markc markc.goldcoast.org --interactive # Select variables to delete
delvconf markc markc.goldcoast.org --all         # Delete all variables

# Bash sourcing example
source <(php artisan shvconf markc markc.goldcoast.org)
echo $VHOST  # Now all variables are available
```

### shvconf Output Formats

- **Default:** Plain sorted `VAR='value'` format (NetServa 1.0 compatible), bash sourceable, passwords visible
- **--table:** Grouped table view with password masking (for display only)
- **--json:** JSON format for programmatic use
- **Variable only:** Shows just the value (e.g., `shvconf markc example.com WPATH` outputs `/srv/example.com/web`)

**Note:** Variables are sourced but not exported. To export: `set -a; source <(shvconf ...); set +a` or export individually.

### Implementation Rules

1. **NO optional flags for vnode/vhost** - they MUST be positional arguments
2. **Order is sacred** - always `<vnode>` then `<vhost>`
3. **shvhost exception** - both arguments optional for listing modes
4. **NO --shost or --vnode flags** - removed from all commands
5. **Update ALL new commands** to follow this pattern

### Laravel Signature Examples

```php
// Required positional arguments
protected $signature = 'addvhost {vnode : SSH host/VNode identifier}
                                 {vhost : Domain name to add}';

// Optional positional arguments (shvhost only)
protected $signature = 'shvhost {vnode? : SSH host/VNode identifier}
                                {vhost? : Domain name to show}';

// With options
protected $signature = 'chvhost {vnode : SSH host/VNode identifier}
                                {vhost : Domain name to update}
                                {--php-version= : Update PHP version}
                                {--ssl= : Enable/disable SSL}';
```

---

## Testing Standards

### Pest 4.0 Testing (MANDATORY)

**Policy:** ALL new features, services, commands, resources MUST include comprehensive Pest 4.0 tests

**Coverage:** Feature tests (user-facing), Unit tests (services/models), Browser tests (UI workflows), API tests

**Workflow:** TDD encouraged → frequent testing during development → 100% coverage → full test suite before commit

**Test location:** `packages/*/tests/` folders

---

## Security & Best Practices

### Security

- **Passwords**: Auto-generated via `/dev/urandom`
- **Permissions**: SSH files 600/700, scripts 755
- **Credentials**: NEVER hardcoded - store in database (`vconfs` table)
- **Private files**: Use `*/private/` directories (gitignored)

### Repository Sanitization (Public GitHub)

- Real domains → `example.com`, `example.net`
- Real IPs → `192.168.100.0/24` range
- Sensitive vars → `_VAR_NAME` placeholders

### Testing & Quality

- **Pest 4.0**: ALL features MUST have comprehensive tests
- **Test location**: `packages/*/tests/` folders
- **Pint formatting**: Run before commits (`vendor/bin/pint`)
- **License**: MIT with copyright headers (1995-2025)

---

## Development Guidelines

**Filament 4.0:** Always use `search-docs` MCP tool for documentation, use stable patterns, leverage Livewire

**SSH:** phpseclib 3.x, certificate-free, reliability over optimization

**Testing:** Pest 4.0 mandatory, mock SSH for speed, test CLI + web interfaces

**Philosophy:** Reference existing implementations, plugin-based architecture, maintain functionality, enhance UX with Laravel Prompts

---

## Shell Enhancement System

### Core Components (`~/.rc/`)

- **`_shrc`** - Main shell resource file with cross-platform compatibility
- **`_myrc`** - User customization template
- **`shm`** - SSH management utility

### Cross-Platform Support

- **Debian/Ubuntu**: apt | **Arch/CachyOS**: pacman/yay | **Alpine**: apk | **OpenWRT**: opkg

### Key Commands

- `i/r/s/u` - Install/Remove/Search/Update packages
- `sc <action> <service>` - Service control (systemd/OpenRC/OpenWRT)
- `sx <host> <command>` - Remote execution via SSH
- `f <pattern>` - Find files recursively

---

## Development Environment

### OS Support

- **Development**: CachyOS (Arch-based)
- **Containers**: Alpine Linux (LXC/Incus)
- **VMs/VPS**: Debian Trixie, Proxmox
- **Legacy**: OpenWrt support

### Infrastructure Stack

- **Web**: Nginx + PHP-FPM
- **Mail**: Postfix + Dovecot + PowerDNS + RSpamd
- **Database**: MySQL/MariaDB, SQLite
- **Frontend**: Laravel 12 + Filament 4.0 + TailwindCSS

### Central Workstation Pattern (MANDATORY)

**ALL NS commands execute FROM workstation TO remote servers via SSH.**

```bash
# ✅ CORRECT - Execute from workstation
user@workstation:~/.ns$ php artisan addvhost markc example.com
user@workstation:~/.ns$ php artisan chperms markc example.com

# ❌ WRONG - Never copy scripts to remote servers
scp script.sh remote:/tmp/ && ssh remote '/tmp/script.sh'
```

**Architecture:** Workstation (`~/.ns/`) → SSH → Remote Server (`~/.rc/` only)

---

## Laravel Boost MCP Tools

**Essential Tools:**
- `search-docs` (use before coding)
- `tinker` (debugging)
- `database-schema` (before migrations)
- `browser-logs` (frontend issues)
- `database-query` (data queries)
- `list-routes`
- `list-artisan-commands`
- `application-info`

**Best Practice:** Always use `search-docs` before implementing any Laravel ecosystem features

---

## Quick Command Reference

### Common Commands
```bash
# Discovery & Setup
php artisan fleet:discover --vnode=markc
php artisan addvhost markc example.com
php artisan chperms markc example.com

# VConf Management
php artisan shvconf markc example.com              # Show all variables
php artisan shvconf markc example.com WPATH        # Show specific variable
php artisan chvconf markc example.com WPATH /srv/example.com/web

# Development
php artisan serve --port=8888    # Web interface (Filament)
php artisan test                 # Run Pest tests
vendor/bin/pint                  # Format code (Laravel Pint)
```

---

**Document Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
