# NetServa 3.0 - Essential Claude Code Rules

**📚 COMPLETE DOCUMENTATION:** `resources/docs/NetServa_3.0_Coding_Style.md`

---

## 🔒 Directory Access Policy (CRITICAL)

**✅ ALLOWED:** `~/.ns/`, `~/.rc/` and `~/Dev/' only
**❌ FORBIDDEN:** All other `/home/markc/` directories require explicit user permission

---

## 📦 NetServa 3.0 Package Architecture Map

**CRITICAL: Check this BEFORE building new functionality - avoid duplicate implementations!**

### Foundation Layer (All packages depend on these)

| Package | Purpose | Key Components | When to Use |
|---------|---------|----------------|-------------|
| **netserva/core** | SSH connections, base models, shared utilities | `RemoteConnectionService`, `SshTunnelService`, `SshHost` model | ALL remote operations, SSH tunnels (PowerDNS, MySQL) |
| **netserva/config** | Configuration & secrets management (STANDALONE) | Database orchestration, unified config | Standalone deployments, CMS-only setups |

### Infrastructure Layer (Physical & Network)

| Package | Purpose | Key Components | When to Use |
|---------|---------|----------------|-------------|
| **netserva/fleet** | Infrastructure topology & orchestration | `FleetVenue`, `FleetVsite`, `FleetVnode`, `FleetVhost` | Track VMs/containers, multi-package orchestration, discovery |
| **netserva/ipam** | IPv4/IPv6 IP allocation tracking | `IpNetwork`, `IpAddress`, `IpReservation` | IP allocation, subnet management, IPv6 reverse zone calculation |
| **netserva/dns** | DNS record management (PowerDNS, Cloudflare) | `DnsProvider`, `DnsZone`, `DnsRecord`, auto-PTR/FCrDNS | All DNS operations, PTR records, DNSSEC, domain registration |
| **netserva/wg** | WireGuard VPN management | WireGuard server/peer management | VPN infrastructure, secure remote access |

### Service Layer (Application Services)

| Package | Purpose | Key Components | When to Use |
|---------|---------|----------------|-------------|
| **netserva/mail** | Email server management | `MailServer`, `Mailbox`, `MailDomain`, Postfix/Dovecot config | Mail infrastructure, SMTP/IMAP, mailbox admin |
| **netserva/web** | Web server orchestration | Nginx, Apache, Let's Encrypt SSL | Web hosting, SSL certificates, web server config |
| **netserva/cms** | Standalone CMS (does NOT use core) | Posts, Pages, Themes - **Uses netserva/config instead** | Blog/content sites, standalone deployments |

### Operations Layer (Monitoring & Automation)

| Package | Purpose | Key Components | When to Use |
|---------|---------|----------------|-------------|
| **netserva/ops** | Monitoring, backups, analytics | `MonitoringCheck`, `BackupJob`, `AlertRule`, `Incident` | Infrastructure monitoring, backup management, observability |
| **netserva/cron** | Task automation & scheduling | Cron job orchestration, automation workflows | Scheduled tasks, automation across infrastructure |

### User Interface & Integration

| Package | Purpose | Key Components | When to Use |
|---------|---------|----------------|-------------|
| **netserva/admin** | Filament admin panel | Settings, plugin management | Main admin UI, global settings |
| **netserva/cli** | Unified CLI interfaces | Laravel Prompts-based commands | Interactive command-line tools |
| **netserva/platform** | Full platform suite | Complete infrastructure management | All-in-one deployments |

### 🔑 Key Architectural Rules

1. **netserva/core provides ALL SSH** - Never duplicate SSH logic in other packages
2. **netserva/cms is STANDALONE** - Does NOT depend on core, uses netserva/config instead
3. **netserva/fleet orchestrates** - Multi-package workflows live here (e.g., IPv6 PTR configuration)
4. **Check this map FIRST** - Before implementing features, verify no package already provides it

### Common Package Combinations

| Use Case | Packages Used |
|----------|---------------|
| **IPv6 PTR Configuration** | fleet (orchestration) + ipam (IPv6 utils) + dns (PTR records) + core (SSH) + mail (Postfix config) |
| **Mail Server Setup** | fleet (infrastructure) + mail (services) + dns (MX/SPF) + ipam (IP allocation) + core (SSH) |
| **Standalone CMS** | cms + config (NO core dependency!) |
| **Full Infrastructure** | platform (everything) |

---

## 🔧 DRY Principle: Service Layer Architecture (CRITICAL)

**Business logic MUST be shared between CLI commands and Filament resources - NO DUPLICATION!**

### The Service Layer Pattern

```
┌─────────────────────────────────────────────────────────────┐
│  CLI Command (addvnode)          Filament Resource          │
│       ↓                                  ↓                   │
│       └──────────────┬───────────────────┘                   │
│                      ↓                                       │
│            VNodeService::create()  ← SINGLE SOURCE OF TRUTH  │
│                      ↓                                       │
│         Database, Remote SSH, Validation, etc.              │
└─────────────────────────────────────────────────────────────┘
```

### Why This Matters

With **250+ CRUD commands** AND **Filament resources**, duplicating logic means:
- ❌ Fixing bugs in TWO places
- ❌ Adding features in TWO places
- ❌ Testing logic in TWO places
- ❌ Different behavior between CLI and UI

**Instead:** One service, called from both environments.

### ✅ CORRECT Pattern

```php
// packages/netserva-fleet/src/Services/VNodeService.php
class VNodeService
{
    public function create(array $data): FleetVnode
    {
        // ALL business logic here
        $vnode = FleetVnode::create([
            'name' => $data['name'],
            'vsite_id' => $data['vsite_id'],
        ]);

        // Remote setup
        $this->remoteExecution->setupVNode($vnode);

        // DNS registration
        $this->dns->registerVNode($vnode);

        return $vnode;
    }

    public function update(FleetVnode $vnode, array $data): FleetVnode
    {
        // Update logic here
    }

    public function delete(FleetVnode $vnode): bool
    {
        // Deletion logic here
    }
}

// packages/netserva-fleet/src/Console/Commands/AddvnodeCommand.php
class AddvnodeCommand extends Command
{
    public function __construct(
        protected VNodeService $vnodeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // THIN wrapper - just gather input and call service
        $data = [
            'name' => $this->argument('vnode'),
            'vsite_id' => $this->option('vsite-id'),
        ];

        $vnode = $this->vnodeService->create($data);

        $this->info("Created vnode: {$vnode->name}");
        return 0;
    }
}

// packages/netserva-fleet/src/Filament/Resources/FleetVnodeResource/Pages/CreateFleetVnode.php
class CreateFleetVnode extends CreateRecord
{
    public function __construct(
        protected VNodeService $vnodeService
    ) {
        parent::__construct();
    }

    protected function handleRecordCreation(array $data): Model
    {
        // SAME service call as CLI command
        return $this->vnodeService->create($data);
    }
}
```

### ❌ WRONG Pattern (Duplicated Logic)

```php
// ❌ BAD: Business logic in command
class AddvnodeCommand extends Command
{
    public function handle(): int
    {
        // DON'T DO THIS - logic should be in service
        $vnode = FleetVnode::create([...]);
        $this->remoteExecution->setupVNode($vnode);
        $this->dns->registerVNode($vnode);
        return 0;
    }
}

// ❌ BAD: Duplicated logic in Filament
class CreateFleetVnode extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        // DON'T DO THIS - same logic duplicated!
        $vnode = FleetVnode::create($data);
        app(RemoteExecutionService::class)->setupVNode($vnode);
        app(DnsService::class)->registerVNode($vnode);
        return $vnode;
    }
}
```

**Problem:** Now you have to maintain the SAME logic in TWO places!

### What Goes Where?

| Layer | Responsibility | Examples |
|-------|----------------|----------|
| **Service** (`src/Services/`) | Business logic, validation, orchestration | `VNodeService::create()`, `DnsService::createZone()` |
| **Command** (`src/Console/Commands/`) | Input gathering, output formatting, calling service | Laravel Prompts, progress bars, table display |
| **Filament Resource** (`src/Filament/Resources/`) | Form schema, table columns, calling service | Schema definition, filters, actions |
| **Model** (`src/Models/`) | Relationships, scopes, accessors ONLY | `vnode()`, `scopeActive()`, `getNameAttribute()` |

### Service Location Pattern

```
packages/netserva-{package}/
├── src/
│   ├── Console/Commands/
│   │   ├── AddvnodeCommand.php      ← Calls service
│   │   ├── ShvnodeCommand.php       ← Calls service
│   │   └── ChvnodeCommand.php       ← Calls service
│   ├── Filament/Resources/
│   │   └── FleetVnodeResource/
│   │       ├── Pages/
│   │       │   ├── CreateFleetVnode.php  ← Calls service
│   │       │   └── EditFleetVnode.php    ← Calls service
│   └── Services/
│       └── VNodeService.php         ← SINGLE SOURCE OF TRUTH
```

### Testing Benefits

```php
// One service = one set of tests
it('creates vnode with DNS registration', function () {
    $service = app(VNodeService::class);
    $vnode = $service->create(['name' => 'test']);

    expect($vnode->name)->toBe('test');
    // Verify DNS was registered, SSH was setup, etc.
});

// Commands and Filament just test they call the service correctly
it('addvnode command calls service', function () {
    $mock = Mockery::mock(VNodeService::class);
    $mock->shouldReceive('create')->once()->andReturn(new FleetVnode);

    $this->app->instance(VNodeService::class, $mock);

    $this->artisan('addvnode test')->assertExitCode(0);
});
```

### Real-World Examples (Existing Codebase)

```bash
# DNS package follows this pattern
packages/netserva-dns/
├── src/Services/
│   ├── DnsProviderService.php     ← Business logic
│   ├── DnsZoneService.php         ← Business logic
│   └── DnsRecordService.php       ← Business logic
├── Console/Commands/
│   ├── AdddnsCommand.php          ← Calls DnsProviderService
│   ├── AddrecCommand.php          ← Calls DnsRecordService
└── Filament/Resources/
    └── DnsProviderResource/       ← Calls DnsProviderService
```

### When Creating New Features

**ALWAYS follow this checklist:**

1. ✅ Create service first: `{Package}Service.php`
2. ✅ Implement business logic in service with full tests
3. ✅ Create CLI command that calls service (thin wrapper)
4. ✅ Create Filament resource that calls SAME service
5. ✅ Test that both CLI and Filament produce identical results

**NEVER:**
- ❌ Put business logic in commands or Filament resources
- ❌ Duplicate validation between CLI and Filament
- ❌ Duplicate database operations
- ❌ Duplicate remote SSH execution

### Quick Reference

**If you're writing the same code in both a Command and a Filament Resource → IT BELONGS IN A SERVICE!**

---

## 🔥 CRITICAL: Filament v4 & Laravel Ecosystem Patterns (MANDATORY)

**⚠️ ALWAYS FOLLOW THESE RULES - NO EXCEPTIONS:**

1. **Filament v4 Patterns**: When working with Filament admin panels, ALWAYS use the latest Filament v4 patterns and syntax
   - ❌ **NEVER** use Filament v3 syntax or deprecated patterns
   - ✅ **ALWAYS** check existing Filament files in the codebase for current patterns
   - ✅ **CRITICAL**: Multiple form fields mapping to one database column requires:
     - Different field names (e.g., `value_string`, `value_integer`, `value_boolean`)
     - **DO NOT** use `statePath()` - causes hydration conflicts
     - **ALWAYS** use `mutateFormDataBeforeFill()` / `mutateFormDataBeforeSave()` in pages
     - **ALWAYS** use `->mutateRecordDataUsing()` / `->using()` in table actions

2. **Laravel Boost MCP Tool**: ALWAYS use `search-docs` MCP tool FIRST before implementing ANY Laravel or Filament code
   - ✅ **MANDATORY**: Search official docs BEFORE writing code
   - ✅ **MANDATORY**: Search for exact version-specific patterns (Laravel 12, Filament v4, Livewire v3, Pest v4)
   - ❌ **NEVER** guess at syntax or patterns - always verify with `search-docs` first
   - ❌ **NEVER** use web search or memory for Laravel/Filament patterns when MCP tool is available

**Why This Matters**: These tools prevent hours of troubleshooting from using outdated patterns. The `search-docs` MCP tool provides version-specific documentation tailored to this project's exact package versions.

## 🛑 MANDATORY: Filament Resource Creation Checklist

**Before creating ANY Filament resource, page, widget, or component, you MUST follow this process:**

### The Non-Negotiable 5-Step Process:

1. **🛑 STOP** - Do NOT write any code yet. Do NOT use memory or assumptions.

2. **🔍 SEARCH** - Use `search-docs` MCP tool with multiple relevant queries:
   ```php
   mcp__laravel-boost__search-docs([
       "filament resource table",
       "filament resource form",
       "filament resource actions",
       "filament v4 schema components"
   ])
   ```

3. **📖 READ** - Review ALL search results completely. Pay attention to:
   - Import statements (use `Filament\Actions\Action`, NOT `Filament\Tables\Actions\Action`)
   - Method signatures (`form(Schema $schema)`, NOT `form(Form $form)`)
   - API changes (`->recordActions([])`, NOT `->actions([])`)
   - Component namespaces (`Filament\Schemas\Components\Section`, NOT `Filament\Forms\Components\Section`)

4. **✍️ WRITE** - Only NOW write code using exact patterns from the documentation

5. **✅ VERIFY** - Cross-check against existing resources in `packages/*/src/Filament/Resources/`

### Common Filament v4 Gotchas (That You Keep Getting Wrong):

| ❌ WRONG (Filament v3) | ✅ CORRECT (Filament v4) |
|------------------------|--------------------------|
| `use Filament\Forms\Form;` | `use Filament\Schemas\Schema;` |
| `use Filament\Tables\Actions\Action;` | `use Filament\Actions\Action;` |
| `public static function form(Form $form)` | `public static function form(Schema $schema)` |
| `->schema([...])` (top level) | `->components([...])` (top level) |
| `->actions([...])` (table) | `->recordActions([...])` (table) |
| `->bulkActions([...])` | `->toolbarActions([...])` |
| `Forms\Components\Section::make()` | `Section::make()` (with proper import) |

### Why This Process is NON-NEGOTIABLE:

- **Skipping search-docs costs 10-30 minutes of debugging time PER resource**
- **Using v3 syntax breaks the entire resource and requires complete rewrite**
- **The search-docs tool is specifically designed for this project's exact versions**
- **Memory-based coding ALWAYS produces outdated patterns**

### Example of Correct Process:

```
User: "Create a ThemeResource for managing themes"

Assistant (CORRECT):
"I'll create the ThemeResource using Filament v4 patterns. Let me search the official documentation first."

[Uses search-docs tool:]
search-docs(["filament resource table", "filament resource form", "filament v4 actions"])

[Reads results completely]

[Then writes ThemeResource.php using EXACT patterns from docs]
```

**If you skip this process, you WILL write v3 code and waste 20+ minutes fixing it.**

---

## 🚨 Mandatory Architecture Rules

1. **Database-First**: ALL vhost config/credentials stored in `vconfs` table - NEVER in files
2. **Remote SSH**: ALL remote scripts MUST use `RemoteExecutionService::executeScript()` heredoc method
3. **CLI Arguments**: ALL commands use `<command> <vnode> <vhost> [options]` - NO --vnode/--shost flags
4. **Execution Pattern**: Commands run FROM workstation TO remote servers via SSH - NEVER copy scripts to remotes
5. **Service Control**: ALWAYS use `sc()` function for service management - works across Alpine/Debian/OpenWrt
6. **Laravel Boost**: ALWAYS use `search-docs` MCP tool before implementing Laravel ecosystem features
7. **Testing**: ALL new features MUST include comprehensive Pest 4.0 tests in `packages/*/tests/`
8. **Platform Schema**: 6 layers - `venue → vsite → vnode → vhost + vconf → vserv`

---

## 🎯 CRUD Command Naming Convention (CRITICAL)

**ALL CRUD commands MUST follow this exact pattern - NO EXCEPTIONS:**

```
add<resource>   - Create new resource
sh<resource>    - Show/list resource(s)
ch<resource>    - Change/update resource
del<resource>   - Delete resource
```

### Why This Pattern?

With **~250+ CRUD commands** across 14 packages, this provides:
- ✅ **Alphabetical grouping** in `php artisan list` (all add* together, all sh* together)
- ✅ **Muscle memory** - always "add" to create, "sh" to show, "ch" to change, "del" to delete
- ✅ **Predictable** - `addvnode` exists, so `shvnode`, `chvnode`, `delvnode` must exist too
- ✅ **Concise** - short prefixes (add vs create, sh vs show/list, ch vs change/update, del vs delete)

### Examples (Existing Pattern)

```bash
# DNS Management
adddns, shdns, chdns, deldns           # Providers
addzone, shzone, chzone, delzone       # Zones
addrec, shrec, chrec, delrec           # Records

# Fleet Management
addvnode, shvnode, chvnode, delvnode   # Virtual nodes
addvsite, shvsite, chvsite, delvsite   # Virtual sites
addvhost, shvhost, chvhost, delvhost   # Virtual hosts

# Mail Management
addvmail, shvmail, chvmail, delvmail   # Virtual mailboxes
addvalias, shvalias, chvalias, delvalias # Mail aliases

# IPAM (when implemented)
addnetwork, shnetwork, chnetwork, delnetwork  # IP networks
addip, ship, chip, delip                       # IP addresses
```

### ❌ DO NOT Create Commands Like:

```bash
# WRONG - Inconsistent verbs
create-vnode, new-vnode, make-vnode    # Use addvnode
show-vnode, list-vnode, get-vnode      # Use shvnode
update-vnode, modify-vnode, edit-vnode # Use chvnode
remove-vnode, destroy-vnode            # Use delvnode

# WRONG - Laravel-style namespacing (too verbose with 250+ commands)
vnode:create, vnode:list, vnode:update, vnode:delete
dns:provider:create, dns:provider:show

# WRONG - Mixed conventions
addvnode but updatevnode               # Must be chvnode
createzone but shzone                  # Must be addzone
```

### ✅ CORRECT Pattern

```bash
# If you're creating DNS record functionality:
addrec    # Not: create-record, dns:record:create, new-record
shrec     # Not: show-record, list-records, dns:record:list
chrec     # Not: update-record, modify-record, dns:record:update
delrec    # Not: delete-record, remove-record, dns:record:delete

# If you're creating IPAM network functionality:
addnetwork  # Not: create-network, ipam:network:create
shnetwork   # Not: show-network, list-networks
chnetwork   # Not: update-network, change-network
delnetwork  # Not: delete-network, remove-network
```

### Non-CRUD Commands (Allowed Exceptions)

**Only non-CRUD operations can use different naming:**

```bash
# Discovery/Import (not CRUD)
fleet:discover
fleet:import-vhosts

# Configuration/Setup (not CRUD)
fleet:ipv6-ptr:configure
mail:configure-dkim

# Operational Tasks (not CRUD)
ops:backup-now
dns:verify-fcrdns

# Testing/Validation (not CRUD)
validate
test-connection
```

**Rule of thumb:** If it's **Create/Read/Update/Delete** → Use **add/sh/ch/del** prefix
If it's **anything else** → Use descriptive name with colons or hyphens

### Command Discovery

With this pattern, users can:
```bash
php artisan list | grep ^add    # All creation commands
php artisan list | grep ^sh     # All show/list commands
php artisan list | grep ^ch     # All change/update commands
php artisan list | grep ^del    # All deletion commands
```

**This pattern is MANDATORY for all NetServa packages.**

---

## 🎯 Technology Stack

Laravel 12 + Filament 4.0 + Pest 4.0 + Laravel Prompts + phpseclib 3.x | SQLite (dev) / MySQL (prod)

---

## 📄 Documentation Standards

**Filename Convention**: ALL documentation files MUST use normalized naming:
- **Format**: `YYYY-MM-DD_lowercase-with-hyphens.md`
- **Date**: Use file creation/modification date (derived from filesystem)
- **Examples**:
  - ✅ `2025-11-05_database-backup-guide.md`
  - ✅ `2025-10-08_netserva-3.0-setup.md`
  - ❌ `DATABASE_BACKUP_GUIDE.md`
  - ❌ `NetServa-3.0-Setup.md`
  - ❌ `ssh_execution_architecture.md`

**Applies to**:
- `resources/docs/**/*.md` - All documentation
- `.claude/journal/*.md` - Session journals (already normalized)
- Any new markdown files created in the project

---

## 📝 Essential Commands

```bash
# Laravel/Testing
php artisan fleet:discover --vnode=markc
php artisan test --filter=TestName
vendor/bin/pint --dirty

# Remote Shell Commands (ALWAYS use sx)
sx <vnode> <command> [args...]        # Interactive shell with full alias/function support

# Common Examples (NOTE: Quote functions/aliases to prevent local execution!)
sx nsorg u                            # Update packages (simple alias, no quotes needed)
sx nsorg l                            # View logs (simple alias)
sx nsorg i nginx                      # Install package (simple alias)
sx nsorg 'sc reload postfix'          # Reload service (MUST QUOTE - function!)
sx nsorg 'sc status nginx'            # Check service status (MUST QUOTE)
sx nsorg 'sc restart dovecot'         # Restart service (MUST QUOTE)

# CRITICAL: Why You Must Quote Functions
# When calling shell functions like sc(), ALWAYS use quotes:
#   ✅ CORRECT: sx gw 'sc status nginx'   (quotes prevent local sc() execution)
#   ❌ WRONG:   sx gw sc status nginx     (local sc() runs first, breaks command)

# Why sx is Better Than ssh:
# ✅ All aliases available (u, l, i, r, s)
# ✅ All functions available (sc, etc) when quoted
# ✅ Works with interactive commands
# ✅ Cleaner output (filters terminal warnings)

# SCP Between Remote Servers (Magic Trick!)
scp -r source_vnode:/path/to/dir dest_vnode:/path/to/destination/
# Example: Copy spamprobe database between mail servers
scp -r mrn:/srv/renta.net/msg/admin/.spamprobe pbe:/srv/pbe.net.au/msg/admin/
# Note: This works from workstation - scp handles the server-to-server transfer automatically!
# Use -r for recursive directory copies, single files don't need it
```

---

## 🔧 Shell Configuration Workflow (CRITICAL)

### The ~/.rc/ Ecosystem
NetServa uses a centralized shell configuration system that syncs across all servers:

**Workflow:**
1. **Edit locally**: Add aliases/functions to `~/.rc/_shrc` on workstation
2. **Sync to remote**: Run `~/.rc/rcm sync <vnode>`
3. **Use immediately**: `sx <vnode> <new_alias_or_function>`

**Example:**
```bash
# 1. Add new function to ~/.rc/_shrc
vi ~/.rc/_shrc
# Add: myfunction() { echo "Hello from $HOSTNAME"; }

# 2. Sync to remote server
~/.rc/rcm sync nsorg

# 3. Use immediately on remote
sx nsorg myfunction
# Output: Hello from nsorg
```

### BASH_ENV Configuration
All remote servers MUST have `BASH_ENV` exported in `~/.bash_profile`:

```bash
# ~/.bash_profile on remote servers
export BASH_ENV=~/.rc/_shrc
```

**Why This Matters:**
- Enables `sx` to access all aliases and functions via interactive shell (`bash -ci`)
- Makes functions available for direct `ssh` commands (though `sx` is preferred)
- Required for `RemoteExecutionService` to use cross-platform functions

**Initial Setup for New Remote Servers:**
1. Sync shell config: `~/.rc/rcm sync <vnode>`
2. Add to remote's `~/.bash_profile`: `export BASH_ENV=~/.rc/_shrc`
3. Test: `sx <vnode> sc status nginx`

### Key Files
- **`~/.rc/_shrc`** - Master shell config (aliases, functions, OS detection)
- **`~/.rc/rcm`** - Sync tool to deploy _shrc to remote servers
- **`~/.myrc`** - Machine-local customizations (never synced)
- **`~/.bash_profile`** - Loads _shrc and sets BASH_ENV on remotes

---

## ❌ Do NOT

- Never hardcode credentials (use vconfs table)
- Never copy scripts to remote servers (execute from workstation)
- Never use file-based config (use database)
- Never skip tests (100% coverage required)
- Never use Filament v3 syntax (ALWAYS use v4)
- Never use systemctl/rc-service directly (ALWAYS use `sc()` function)

---

## 📚 Documentation References

- Architecture: `resources/docs/SSH_EXECUTION_ARCHITECTURE.md`
- VHost Variables: `resources/docs/VHOST-VARIABLES.md`
- AI Workflows: `resources/docs/ai/proven-workflows.md`
- Testing Strategy: `resources/docs/reference/testing_strategy.md`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.14
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

### Troubleshooting: MCP Tools Not Available
If the Laravel Boost MCP tools (like `search-docs`, `database-query`, `tinker`, etc.) are not available:

1. **Check `~/.claude.json`**: The server may be disabled in the configuration
2. **Fix**: Manually edit `~/.claude.json`:
   - Remove `"laravel-boost"` from the `disabledMcpServers` array, OR
   - Change it to an empty array: `"disabledMcpServers": []`
3. **Restart**: Claude Code needs to be restarted after changing the configuration

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
