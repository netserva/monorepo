# BinaryLane Integration for NetServa 3.0

## Executive Summary

Integrate BinaryLane VPS provider API directly into NetServa 3.0's Fleet package, enabling server provisioning, management, and auto-discovery from within the Laravel ecosystem. This replaces the need for external CLI tools while providing full Filament UI and Artisan command access.

---

## Architecture Overview

### BinaryLane API Analysis

**API Specification:** OpenAPI 3.0.4 at `https://api.binarylane.com.au/reference/openapi.json`

**Total Endpoints:** 94 (we'll implement ~20 core operations initially)

**Core Resources:**
| Resource | Endpoints | Priority |
|----------|-----------|----------|
| Servers | CRUD + actions (power, resize, backup) | P1 |
| Sizes | List available VPS sizes | P1 |
| Images | List OS images (Debian, Ubuntu, etc.) | P1 |
| Regions | List data center regions | P1 |
| SSH Keys | Manage SSH keys | P1 |
| VPCs | Virtual private clouds | P2 |
| Domains | DNS management | P3 (we have PowerDNS) |
| Load Balancers | LB management | P3 |
| Actions | Async operation tracking | P1 |

### Integration Points with Fleet

```
┌─────────────────────────────────────────────────────────────┐
│                    NetServa Fleet                            │
├─────────────────────────────────────────────────────────────┤
│  FleetVenue (Organization/Account)                          │
│    └── FleetVsite (provider=binarylane, technology=vps)     │
│          ├── api_endpoint: https://api.binarylane.com.au   │
│          ├── api_credentials: {token: "..."}  (encrypted)  │
│          └── FleetVnode (synced from BinaryLane servers)   │
│                ├── instance_id: 427124 (BL server ID)      │
│                ├── ip_address: 43.224.182.189              │
│                └── FleetVhost (domains on this server)     │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Core Service Layer (Day 1)

### 1.1 BinaryLaneService

**Location:** `packages/netserva-fleet/src/Services/BinaryLaneService.php`

**Responsibilities:**
- HTTP client wrapper with authentication
- Response normalization
- Error handling
- Rate limiting awareness
- Caching for static data (sizes, images, regions)

**Core Methods:**
```php
class BinaryLaneService
{
    // Configuration
    public function __construct(?FleetVsite $vsite = null)
    public function setToken(string $token): self

    // Servers
    public function listServers(): Collection
    public function getServer(int $id): array
    public function createServer(array $data): array
    public function deleteServer(int $id): bool
    public function serverAction(int $id, string $action, array $params = []): array

    // Reference Data (cached)
    public function sizes(): Collection
    public function images(string $type = 'distribution'): Collection
    public function regions(): Collection
    public function sshKeys(): Collection

    // Actions
    public function getAction(int $id): array
    public function waitForAction(int $id, int $timeout = 300): array

    // VPCs
    public function vpcs(): Collection
    public function getVpc(int $id): array

    // Account
    public function account(): array
    public function balance(): array
}
```

### 1.2 BinaryLane DTOs

**Location:** `packages/netserva-fleet/src/DTOs/BinaryLane/`

```php
// Server.php
readonly class Server {
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
        public string $size,
        public string $region,
        public ?string $ipv4,
        public ?string $ipv6,
        public ?int $vpcId,
        public Carbon $createdAt,
        public array $raw,
    ) {}

    public static function fromApi(array $data): self
}

// Size.php, Image.php, Region.php similarly
```

### 1.3 Configuration

**Location:** `packages/netserva-fleet/config/fleet.php`

```php
'binarylane' => [
    'api_url' => env('BINARYLANE_API_URL', 'https://api.binarylane.com.au/v2'),
    'timeout' => 30,
    'cache_ttl' => 3600, // Cache sizes/images/regions for 1 hour
    'default_region' => 'syd',
    'default_ssh_keys' => [], // Key IDs to add to all servers
],
```

---

## Phase 2: CLI Commands (Day 1-2)

### 2.1 Command Structure

Following NetServa 3.0 CRUD pattern: `bl:*` namespace for BinaryLane-specific operations.

| Command | Signature | Description |
|---------|-----------|-------------|
| `bl:list` | `bl:list {--vsite=} {--format=table}` | List all servers |
| `bl:info` | `bl:info {server}` | Show server details |
| `bl:create` | `bl:create {name} {size} {image} {region} {--ssh-key=*} {--ipv6} {--vpc=} {--user-data=} {--sync}` | Create server |
| `bl:delete` | `bl:delete {server} {--force}` | Delete server |
| `bl:power` | `bl:power {server} {action}` | Power on/off/cycle/reboot |
| `bl:resize` | `bl:resize {server} {size}` | Resize server |
| `bl:sizes` | `bl:sizes {--region=}` | List available sizes |
| `bl:images` | `bl:images {--type=distribution}` | List available images |
| `bl:regions` | `bl:regions` | List regions |
| `bl:keys` | `bl:keys` | List SSH keys |
| `bl:sync` | `bl:sync {--vsite=}` | Sync BinaryLane servers to VNodes |
| `bl:setup` | `bl:setup {venue} {name}` | Interactive VSite setup for BinaryLane |

### 2.2 Example Command Implementation

```php
// BlListCommand.php
class BlListCommand extends Command
{
    protected $signature = 'bl:list
                            {--vsite= : VSite to use (must be binarylane provider)}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'List BinaryLane servers';

    public function handle(BinaryLaneService $service): int
    {
        $vsite = $this->resolveVsite();

        if ($vsite) {
            $service->setToken($vsite->getDecryptedCredentials()['token']);
        }

        $servers = $service->listServers();

        // Display with format option...

        return Command::SUCCESS;
    }
}
```

### 2.3 Auto-Sync Feature

`bl:sync` command will:
1. Fetch all servers from BinaryLane API
2. Match with existing VNodes by `instance_id` or name
3. Create new VNodes for untracked servers
4. Update existing VNode metadata (IP, status, specs)
5. Optionally create SSH hosts for new servers
6. Mark VNodes as inactive if server deleted

---

## Phase 3: Fleet Integration (Day 2)

### 3.1 VSite Provider Integration

Extend VSite to recognize `provider=binarylane`:

```php
// FleetVsite model - add method
public function getBinaryLaneService(): ?BinaryLaneService
{
    if ($this->provider !== 'binarylane') {
        return null;
    }

    return app(BinaryLaneService::class)->setToken(
        $this->getDecryptedCredentials()['token']
    );
}

public function canProvisionServers(): bool
{
    return in_array($this->provider, ['binarylane', 'proxmox']);
}
```

### 3.2 VNode Extensions

Add BinaryLane-specific fields to VNode:

```php
// Migration: add columns
$table->unsignedBigInteger('bl_server_id')->nullable();
$table->string('bl_size_slug')->nullable();
$table->string('bl_region')->nullable();
$table->string('bl_image')->nullable();
$table->timestamp('bl_synced_at')->nullable();

// Model: relationship
public function binaryLaneServer(): array
{
    if (!$this->bl_server_id || !$this->vsite?->getBinaryLaneService()) {
        return [];
    }

    return $this->vsite->getBinaryLaneService()->getServer($this->bl_server_id);
}
```

### 3.3 Provisioning Workflow

**Create VNode with BinaryLane provisioning:**

```bash
# Option 1: Create server then register as VNode
php artisan bl:create ns4.spiderweb.com.au std-min debian-13 syd --sync

# Option 2: Create VNode that provisions BinaryLane server
php artisan addvnode binarylane-sydney ns4 --provision \
    --bl-size=std-min --bl-image=debian-13 --bl-region=syd
```

---

## Phase 4: Filament UI (Day 2-3)

### 4.1 BinaryLane Resource

**Location:** `packages/netserva-fleet/src/Filament/Resources/BinaryLaneServerResource.php`

**Features:**
- List all BinaryLane servers with status badges
- Create new servers via form wizard
- Server detail view with real-time status
- Power actions (on/off/reboot) via header actions
- Resize action with size selector
- Delete with confirmation
- Link to associated VNode if synced

### 4.2 VSite Integration

Extend `FleetVsiteResource` for BinaryLane VSites:
- "Test Connection" action to verify API token
- "Sync Servers" action to pull servers as VNodes
- Show BinaryLane account balance widget

### 4.3 Widgets

```php
// BinaryLaneOverviewWidget.php
class BinaryLaneOverviewWidget extends Widget
{
    // Shows: Total servers, Monthly cost, Account balance
    // Quick actions: Create server, Sync all
}
```

---

## Phase 5: Testing (Day 3)

### 5.1 Unit Tests

```php
// tests/Unit/Services/BinaryLaneServiceTest.php
describe('BinaryLaneService', function () {
    beforeEach(function () {
        Http::fake([
            'api.binarylane.com.au/v2/servers' => Http::response([
                'servers' => [
                    ['id' => 1, 'name' => 'test', 'status' => 'active', ...]
                ]
            ]),
            // ... more fakes
        ]);
    });

    it('lists servers', function () {
        $service = new BinaryLaneService();
        $service->setToken('test-token');

        $servers = $service->listServers();

        expect($servers)->toHaveCount(1);
        expect($servers->first())->toBeInstanceOf(Server::class);
    });

    it('creates server with required fields', function () { ... });
    it('handles API errors gracefully', function () { ... });
    it('caches static data', function () { ... });
});
```

### 5.2 Feature Tests

```php
// tests/Feature/Commands/BlListCommandTest.php
it('lists all binarylane servers', function () {
    Http::fake([...]);

    $this->artisan('bl:list')
        ->expectsTable([...])
        ->assertExitCode(0);
});

it('filters by vsite', function () { ... });
it('outputs json format', function () { ... });
```

### 5.3 Filament Tests

```php
// tests/Feature/Filament/BinaryLaneServerResourceTest.php
it('can list servers', function () {
    Http::fake([...]);

    livewire(ListBinaryLaneServers::class)
        ->assertCanSeeTableRecords([...]);
});

it('can create server via wizard', function () { ... });
it('can perform power actions', function () { ... });
```

---

## Phase 6: Advanced Features (Future)

### 6.1 Server Actions (P2)

- Backup management (enable/disable/restore)
- Snapshot creation
- Console access URL
- Firewall rules
- Kernel selection

### 6.2 Automation (P2)

- Auto-provision on VNode create
- Auto-configure DNS (A record) on server create
- Auto-setup SSH host with discovered IP
- Cloud-init user-data templates

### 6.3 Monitoring (P3)

- Server metrics polling
- Bandwidth usage tracking
- Alert threshold configuration
- Integration with ops package

---

## File Structure

```
packages/netserva-fleet/
├── src/
│   ├── Services/
│   │   └── BinaryLaneService.php           # Core API client
│   ├── DTOs/
│   │   └── BinaryLane/
│   │       ├── Server.php
│   │       ├── Size.php
│   │       ├── Image.php
│   │       ├── Region.php
│   │       └── Action.php
│   ├── Console/Commands/
│   │   ├── BlListCommand.php               # bl:list
│   │   ├── BlInfoCommand.php               # bl:info
│   │   ├── BlCreateCommand.php             # bl:create
│   │   ├── BlDeleteCommand.php             # bl:delete
│   │   ├── BlPowerCommand.php              # bl:power
│   │   ├── BlResizeCommand.php             # bl:resize
│   │   ├── BlSizesCommand.php              # bl:sizes
│   │   ├── BlImagesCommand.php             # bl:images
│   │   ├── BlRegionsCommand.php            # bl:regions
│   │   ├── BlKeysCommand.php               # bl:keys
│   │   ├── BlSyncCommand.php               # bl:sync
│   │   └── BlSetupCommand.php              # bl:setup
│   └── Filament/
│       └── Resources/
│           └── BinaryLaneServerResource.php
│               └── Pages/
│                   ├── ListBinaryLaneServers.php
│                   ├── CreateBinaryLaneServer.php
│                   └── ViewBinaryLaneServer.php
├── config/
│   └── fleet.php                           # Add binarylane section
├── database/migrations/
│   └── 2025_12_02_add_binarylane_fields_to_vnodes.php
└── tests/
    ├── Unit/
    │   └── Services/
    │       └── BinaryLaneServiceTest.php
    └── Feature/
        ├── Commands/
        │   ├── BlListCommandTest.php
        │   ├── BlCreateCommandTest.php
        │   └── BlSyncCommandTest.php
        └── Filament/
            └── BinaryLaneServerResourceTest.php
```

---

## Implementation Order

### Day 1 (Core)
1. [ ] `BinaryLaneService.php` - API client with HTTP facade
2. [ ] DTOs for Server, Size, Image, Region
3. [ ] Config additions to `fleet.php`
4. [ ] `bl:list` command
5. [ ] `bl:sizes`, `bl:images`, `bl:regions` commands
6. [ ] Basic unit tests with HTTP fakes

### Day 2 (CRUD + Integration)
7. [ ] `bl:info`, `bl:create`, `bl:delete` commands
8. [ ] `bl:power`, `bl:resize` commands
9. [ ] `bl:sync` command with VNode integration
10. [ ] `bl:setup` interactive setup
11. [ ] Migration for VNode BinaryLane fields
12. [ ] Feature tests for commands

### Day 3 (UI + Polish)
13. [ ] `BinaryLaneServerResource` Filament resource
14. [ ] VSite actions (test connection, sync)
15. [ ] Overview widget
16. [ ] Filament tests
17. [ ] Documentation
18. [ ] Remove bash script `~/.rc/bl`

---

## Success Criteria

1. **CLI Parity**: All operations from bash `bl` script available via Artisan
2. **Fleet Integration**: BinaryLane servers sync as VNodes automatically
3. **Database-First**: All server metadata stored in fleet tables
4. **Full Test Coverage**: Unit + Feature + Filament tests passing
5. **Documentation**: Commands documented with `--help`

---

## Dependencies

- **Existing**: HTTP facade, Fleet models, Filament 4.x
- **New**: None (no external packages required)

---

## Risk Assessment

| Risk | Mitigation |
|------|------------|
| API rate limiting | Implement exponential backoff, cache static data |
| API changes | Pin to v2 endpoint, version DTOs |
| Token security | Use Laravel's encryption, never log tokens |
| Long-running actions | Async tracking with `waitForAction()` |

---

## Questions Before Implementation

1. **SSH Key Management**: Should `bl:create` auto-add default SSH keys from config, or require explicit `--ssh-key` flags?

2. **VNode Auto-Creation**: When syncing, should we auto-create SSH hosts for new servers, or just VNodes?

3. **Naming Convention**: Keep `bl:*` namespace or integrate into `fleet:bl-*`?

4. **VPC Support**: Include VPC selection in initial implementation or defer?
