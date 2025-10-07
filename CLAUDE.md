# CLAUDE.md - NetServa 3.0 Platform Guide

This file provides guidance to Claude Code for the NetServa 3.0 Platform (NS) - a modern plugin-based Laravel + Filament 4.0 infrastructure management system.

## 🔒 Directory Access Policy

**CRITICAL**: Claude Code is restricted to work ONLY within specific directories:

### ✅ Allowed Directories (Full Access)
- **`~/.ns/`** - NetServa 3.0 Platform management system
- **`~/.rc/`** - Shell Enhancement System (foundational utilities)

### ❌ Restricted Access (Requires Explicit Authorization)
- **All other directories in `/home/markc/`** - Require explicit user permission before reading/writing

## 🏗️ Architecture Overview

### Two-Component System

This is a **dual-component infrastructure management system**:

#### 1. Shell Enhancement System (`~/.rc/`)
- **Purpose**: Foundational shell utilities and cross-platform compatibility layer
- **Components**: Bash aliases, functions, SSH management tools
- **Scope**: General-purpose shell enhancements for any Linux/Unix system

#### 2. NetServa 3.0 Platform (`~/.ns/`)
- **Purpose**: Complete server infrastructure management system
- **Stack**: Laravel 12 + Filament 4.0 + Pest 4.0
- **Architecture**: Plugin-based (11 modules) with database-first configuration
- **Scope**: Proxmox/Incus/VPS/Physical servers, multi-site environments

### Dependency Relationship
- **`~/.ns/`** depends on **`~/.rc/`** for foundational functions
- **`~/.rc/`** is standalone and can be used independently
- Remote servers only need `~/.rc/` (not `~/.ns/`)

## 🎯 Platform Schema Hierarchy

**6-Layer Infrastructure Model:**

```
venue → vsite → vnode → vhost + vconf → vserv
```

1. **venue** - Physical location or datacenter (e.g., `home-lab`, `sydney-dc`)
2. **vsite** - Logical grouping within venue (e.g., `production`, `staging`)
3. **vnode** - Virtual/physical server (e.g., `markc` at 192.168.1.227)
4. **vhost** - Virtual hosting domain (e.g., `markc.goldcoast.org`)
5. **vconf** - Configuration variables (54+ vars in `vconfs` table)
6. **vserv** - Services per vhost (nginx, php-fpm, postfix, dovecot)

## 📋 Technology Stack

**Core:** Laravel 12, Filament 4.0 (stable), Pest 4.0, Laravel Prompts, Laravel Boost MCP, phpseclib 3.x
**Storage:** SQLite (dev), MySQL/MariaDB (production)
**Architecture:** Database-first with `vconfs` table for all configuration
**Infrastructure:** Proxmox/Incus/VPS/Physical servers

## 🔧 Plugin Architecture

**11 NetServa Plugins:**

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

**Auto-Discovery:** Plugins automatically register Filament resources, console commands, and migrations

## 🚀 Laravel Boost MCP Tools (CRITICAL)

**Essential Tools:** `search-docs` (use before coding), `tinker` (debugging), `database-schema` (before migrations), `browser-logs` (frontend issues), `database-query` (data queries), `list-routes`, `list-artisan-commands`, `application-info`

**Best Practice:** Always use `search-docs` before implementing any Laravel ecosystem features

## 🧪 Pest 4.0 Testing (MANDATORY)

**Policy:** ALL new features, services, commands, resources MUST include comprehensive Pest 4.0 tests

**Coverage:** Feature tests (user-facing), Unit tests (services/models), Browser tests (UI workflows), API tests

**Workflow:** TDD encouraged → frequent testing during development → 100% coverage → full test suite before commit

## 📋 Plugin Architecture Components

### Standard Plugin Structure
**Service Providers:** Extend `BaseNsServiceProvider` for auto-migration loading, asset management, command registration

**Plugin Interface:** All plugins implement `NsPluginInterface` with standardized methods (getId, register, boot, enable/disable)

**Auto-Discovery:** AdminPanelProvider automatically discovers and registers all plugin resources, pages, widgets

### Key Patterns
**CLI + Web Integration:** Services work in both CLI commands and Filament resources
**Laravel Prompts:** Beautiful interactive CLI with progress bars and confirmations
**Queue Support:** Long-running operations use Laravel queues
**SSH via phpseclib:** 50ms overhead accepted for certificate-free simplicity

## 🔌 Remote SSH Execution (CRITICAL)

**ALL remote bash scripts MUST use the heredoc-based `executeScript()` method.**

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

## 🗄️ Database-First Architecture (CRITICAL)

**ALL VHost configuration, credentials, and environment variables are stored in the Laravel database.**

### Platform Schema Hierarchy

**6-Layer Infrastructure Model:**

```
venue → vsite → vnode → vhost + vconf → vserv
```

1. **venue** - Physical location/datacenter (e.g., `home-lab`, `sydney-dc`)
2. **vsite** - Logical grouping (e.g., `production`, `staging`)
3. **vnode** - Virtual/physical server (e.g., `markc` at 192.168.1.227)
4. **vhost** - Virtual hosting domain (e.g., `markc.goldcoast.org`)
5. **vconf** - Configuration variables (54+ vars in dedicated `vconfs` table)
6. **vserv** - Services per vhost (nginx, php-fpm, postfix, dovecot)

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

### CLI Command Conventions (CRITICAL)

**ALL NetServa CRUD commands use positional arguments in this exact order:**

```bash
<command> <vnode> <vhost> [options]
```

**VNODE:** SSH host identifier (server/VNode name)
**VHOST:** Domain name (virtual host)

**Core CRUD Commands:**
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

**Examples:**
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

**shvconf Output Formats:**
- **Default:** Plain sorted `VAR='value'` format (NetServa 1.0 compatible), bash sourceable, passwords visible
- **--table:** Grouped table view with password masking (for display only)
- **--json:** JSON format for programmatic use
- **Variable only:** Shows just the value (e.g., `shvconf markc example.com WPATH` outputs `/srv/example.com/web`)

**Note:** Variables are sourced but not exported. To export: `set -a; source <(shvconf ...); set +a` or export individually.

**Implementation Rules:**
1. **NO optional flags for vnode/vhost** - they MUST be positional arguments
2. **Order is sacred** - always `<vnode>` then `<vhost>`
3. **shvhost exception** - both arguments optional for listing modes
4. **NO --shost or --vnode flags** - removed from all commands
5. **Update ALL new commands** to follow this pattern

**Laravel Signature Examples:**
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

## 🎯 Current Status

**✅ Architecture Complete**
- 11 NetServa plugins operational
- Filament 4.0 admin interface
- Pest 4.0 test coverage
- Laravel 12 modern patterns
- CLI + Web dual interface

## 🐚 Shell Enhancement System (`~/.rc/`)

### Core Components
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

## 🖥️ Development Environment

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

## 🚨 Execution Architecture

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

## 🔐 Security & Best Practices

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

## 📝 Development Guidelines

**Filament 4.0:** Always use `search-docs` MCP tool for documentation, use stable patterns, leverage Livewire

**SSH:** phpseclib 3.x, certificate-free, reliability over optimization

**Testing:** Pest 4.0 mandatory, mock SSH for speed, test CLI + web interfaces

**Philosophy:** Reference existing implementations, plugin-based architecture, maintain functionality, enhance UX with Laravel Prompts

## 📚 Quick Reference

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

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.13
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== filament/core rules ===

## Filament
- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan
- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features
- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships
- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>


## Testing
- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>


=== filament/v4 rules ===

## Filament 4

### Important Version 4 Changes
- File visibility is now `private` by default.
- The `deferFilters` method from Filament v3 is now the default behavior in Filament v4, so users must click a button before the filters are applied to the table. To disable this behavior, you can use the `deferFilters(false)` method.
- The `Grid`, `Section`, and `Fieldset` layout components no longer span all columns by default.
- The `all` pagination page method is not available for tables by default.
- All action classes extend `Filament\Actions\Action`. No action classes exist in `Filament\Tables\Actions`.
- The `Form` & `Infolist` layout components have been moved to `Filament\Schemas\Components`, for example `Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.
- A new `Repeater` component for Forms has been added.
- Icons now use the `Filament\Support\Icons\Heroicon` Enum by default. Other options are available and documented.

### Organize Component Classes Structure
- Schema components: `Schemas/Components/`
- Table columns: `Tables/Columns/`
- Table filters: `Tables/Filters/`
- Actions: `Actions/`


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
- ALWAYS use Filament v4 syntax when adding or updating Filament admin panels.
