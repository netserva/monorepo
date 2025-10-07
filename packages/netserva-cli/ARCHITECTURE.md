# NetServa CLI Plugin Architecture

## Purpose

The `netserva-cli` plugin provides **Laravel Prompts console commands** for interactive infrastructure management. It is a **pure PHP/Laravel plugin** with NO bash functions, aliases, or shell scripts.

## Architecture Principles

### 1. Pure Laravel/PHP Plugin
- ✅ Laravel Artisan commands with Laravel Prompts
- ✅ PHP Services shared with Filament admin panels
- ✅ Database-first (all config in Laravel database)
- ✅ Pest 4.0 tests
- ❌ NO bash functions
- ❌ NO bash aliases
- ❌ NO shell scripts

### 2. Shared Services Pattern

All business logic lives in **Services** that are used by BOTH:
- **CLI Commands** (Laravel Prompts interactive workflows)
- **Filament Resources** (Web GUI CRUD panels)

```php
// Service layer (shared)
namespace NetServa\Cli\Services;
class VHostPermissionsService {
    public function fixPermissions(FleetVHost $vhost): array { }
}

// CLI Command (uses service)
namespace NetServa\Cli\Console\Commands;
class ChpermsCommand extends BaseNetServaCommand {
    public function handle(VHostPermissionsService $service) {
        $service->fixPermissions($vhost);
    }
}

// Filament Action (uses same service)
namespace NetServa\Fleet\Filament\Resources\FleetVHostResource;
Action::make('fixPermissions')
    ->action(fn(FleetVHost $record, VHostPermissionsService $service) =>
        $service->fixPermissions($record)
    );
```

### 3. One-to-One CRUD Mapping

Every CLI command has a corresponding Filament admin panel operation:

| CLI Command | Filament Resource | Shared Service |
|-------------|-------------------|----------------|
| `addvhost` | FleetVHostResource::Create | VhostManagementService |
| `shvhost` | FleetVHostResource::List/View | VhostManagementService |
| `chvhost` | FleetVHostResource::Edit | VhostManagementService |
| `delvhost` | FleetVHostResource::Delete | VhostManagementService |
| `chperms` | FleetVHostResource::Action | VHostPermissionsService |
| `addvconf` | FleetVHostResource::Action | DatabaseVhostConfigService |
| `shvconf` | FleetVHostResource::Action | DatabaseVhostConfigService |
| `chvconf` | FleetVHostResource::Action | DatabaseVhostConfigService |
| `delvconf` | FleetVHostResource::Action | DatabaseVhostConfigService |
| `addvmail` | MailboxResource::Create | VmailManagementService |
| `addpw` | SecretResource::Create | (config plugin) |
| `shpw` | SecretResource::View | (config plugin) |
| `chpw` | SecretResource::Edit | (config plugin) |
| `delpw` | SecretResource::Delete | (config plugin) |

## Plugin Scope

### Core Responsibilities

1. **Infrastructure Management**
   - VHost lifecycle (add/change/delete/show)
   - File permissions (chperms)
   - VHost configuration variables (addvconf/shvconf/chvconf/delvconf)
   - Remote execution via SSH (RemoteExecutionService)

2. **Setup & Migration**
   - Server setup orchestration (SetupService)
   - Migration workflows (MigrationService)
   - Platform profile management

3. **SSH & Remote Execution**
   - SSH configuration management (SshConfigService)
   - Tunnel management (TunnelService)
   - Remote script execution (RemoteExecutionService)

4. **User Management**
   - System user creation/management (UserManagementService)
   - Password management (for system users)

### Shared Services (Used by Other Plugins)

- `RemoteExecutionService` - Execute scripts on remote servers
- `SshConfigService` - Manage SSH connections
- `TunnelService` - Create/manage SSH tunnels
- `VHostResolverService` - Resolve vnode/vhost from arguments
- `VhostManagementService` - VHost CRUD operations
- `VHostPermissionsService` - Fix file/directory permissions
- `DatabaseVhostConfigService` - Manage FleetVHost.environment_vars

## Directory Structure

```
packages/netserva-cli/
├── config/
│   └── netserva-cli.php           # Plugin configuration
├── database/
│   ├── factories/                  # Model factories for tests
│   └── migrations/                 # Database migrations
├── src/
│   ├── Commands/
│   │   └── NsCommand.php          # Main 'ns' command
│   ├── Console/
│   │   └── Commands/              # All Artisan commands (add*, sh*, ch*, del*)
│   ├── Contracts/                 # Interfaces
│   ├── Enums/                     # Enums (OsType, NetServaConstants)
│   ├── Exceptions/                # Custom exceptions
│   ├── Filament/
│   │   └── Resources/             # Filament admin resources
│   ├── Models/                    # Eloquent models
│   ├── Services/                  # ⭐ SHARED business logic
│   └── ValueObjects/              # Value objects
└── tests/
    ├── Feature/                   # Feature tests (CLI + Filament)
    └── Unit/                      # Unit tests (Services)
```

## NetServa CRUD Convention

All commands follow the NetServa CRUD pattern:

```bash
# Pattern: <action><entity> <vnode> <vhost> [options]

# CREATE
addvhost <vnode> <vhost>           # Add virtual host
addvconf <vnode> <vhost>           # Initialize configuration
addvmail <vnode> <vhost> <email>   # Add email account

# READ
shvhost [vnode] [vhost]            # Show virtual host(s)
shvconf <vnode> <vhost> [var]      # Show configuration
shpw <vnode> <vhost>               # Show passwords

# UPDATE
chvhost <vnode> <vhost> [options]  # Change virtual host settings
chvconf <vnode> <vhost> <var> <val> # Change configuration variable
chperms <vnode> <vhost>            # Fix permissions (special case)
chpw <vnode> <vhost>               # Change password

# DELETE
delvhost <vnode> <vhost>           # Delete virtual host
delvconf <vnode> <vhost> [var]     # Delete configuration variable(s)
delpw <vnode> <vhost>              # Delete password
```

## Integration with Bash

The ONLY bash integration is via `~/.ns/_nsrc` which provides:

```bash
# Simple wrapper aliases
addvhost() { cd "$NS" && php artisan addvhost "$@"; }
shvhost() { cd "$NS" && php artisan shvhost "$@"; }
chvhost() { cd "$NS" && php artisan chvhost "$@"; }
delvhost() { cd "$NS" && php artisan delvhost "$@"; }
chperms() { cd "$NS" && php artisan chperms "$@"; }

# VHost configuration
addvconf() { cd "$NS" && php artisan addvconf "$@"; }
shvconf() { cd "$NS" && php artisan shvconf "$@"; }
chvconf() { cd "$NS" && php artisan chvconf "$@"; }
delvconf() { cd "$NS" && php artisan delvconf "$@"; }

# Essential environment
export NS="${NS:-$HOME/.ns}"
export NS_DB="$NS/database/database.sqlite"

# Minimal helpers
ns_db() { sqlite3 "$NS_DB" "$@"; }
ns_log() { echo "[$1] $*" | tee -a "$NS/storage/logs/netserva.log"; }
```

**NO** other bash files should exist in the plugin.

## Testing Requirements

Every command and service MUST have comprehensive Pest 4.0 tests:

```php
// Feature test (CLI)
it('creates vhost with Laravel Prompts', function () {
    $this->artisan('addvhost', ['vnode' => 'test', 'vhost' => 'example.com'])
        ->expectsOutput('✅ VHost created successfully')
        ->assertSuccessful();

    expect(FleetVHost::where('domain', 'example.com')->exists())->toBeTrue();
});

// Unit test (Service)
it('fixes permissions on remote server', function () {
    $service = app(VHostPermissionsService::class);
    $vhost = FleetVHost::factory()->create();

    $result = $service->fixPermissions($vhost);

    expect($result['success'])->toBeTrue();
});

// Filament test (Web GUI)
it('creates vhost via Filament panel', function () {
    livewire(FleetVHostResource\Pages\CreateFleetVHost::class)
        ->fillForm(['domain' => 'example.com', 'vnode_id' => $vnode->id])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();
});
```

## Service Implementation Pattern

### RemoteExecutionService - The Foundation

All remote operations use `executeScript()` or `executeScriptWithVhost()`:

```php
use NetServa\Cli\Services\RemoteExecutionService;

class VHostPermissionsService
{
    public function __construct(
        protected RemoteExecutionService $remoteExecution
    ) {}

    public function fixPermissions(FleetVHost $vhost): array
    {
        // Automatically injects environment_vars from database
        return $this->remoteExecution->executeScriptWithVhost(
            host: $vhost->vnode->name,
            vhost: $vhost,
            script: <<<'BASH'
                #!/bin/bash
                set -euo pipefail

                # Environment vars available: $UPATH, $WPATH, $UUSER, etc.
                cd "$UPATH"

                find "$WPATH" -type d -exec chmod 755 {} \;
                find "$WPATH" -type f -exec chmod 644 {} \;
                chown -R "$UUSER:www-data" "$WPATH"

                echo "✅ Permissions fixed"
                BASH,
            asRoot: true
        );
    }
}
```

### Service Dependencies

Services can depend on other services:

```php
class VhostManagementService
{
    public function __construct(
        protected RemoteExecutionService $remoteExecution,
        protected VHostResolverService $resolver,
        protected VHostPermissionsService $permissions,
        protected DatabaseVhostConfigService $config
    ) {}

    public function create(string $vnode, string $domain): FleetVHost
    {
        // Create database record
        $vhost = FleetVHost::create([...]);

        // Initialize configuration
        $this->config->initialize($vhost);

        // Execute remote setup
        $this->remoteExecution->executeScriptWithVhost(...);

        // Fix permissions
        $this->permissions->fixPermissions($vhost);

        return $vhost;
    }
}
```

## Command Implementation Pattern

### Base Command

All commands extend `BaseNetServaCommand`:

```php
namespace NetServa\Cli\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseNetServaCommand extends Command
{
    protected function executeWithContext(callable $callback): int
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            $this->error("❌ {$e->getMessage()}");
            return 1;
        }
    }
}
```

### CRUD Command Pattern

```php
namespace NetServa\Cli\Console\Commands;

class AddvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'addvhost {vnode : VNode name}
                                     {vhost : Domain name}
                                     {--php-version=8.4}';

    protected $description = 'Add virtual host (NetServa CRUD pattern)';

    public function handle(VhostManagementService $service): int
    {
        return $this->executeWithContext(function () use ($service) {
            $vnode = $this->argument('vnode');
            $vhost = $this->argument('vhost');

            // Use Laravel Prompts for interactive workflow
            $phpVersion = $this->option('php-version');

            // Delegate to service
            $result = $service->create($vnode, $vhost, $phpVersion);

            $this->info("✅ VHost {$vhost} created on {$vnode}");

            return 0;
        });
    }
}
```

## Summary

**netserva-cli is a pure PHP/Laravel plugin** providing:
- Interactive CLI commands with Laravel Prompts
- Shared services used by CLI and Filament
- Remote execution orchestration
- Database-first configuration
- Comprehensive test coverage

**No bash code belongs in this plugin** - only in `~/.ns/_nsrc`.
