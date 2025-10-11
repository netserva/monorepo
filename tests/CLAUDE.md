# Testing - Context

**NetServa 3.0 Pest 4.0 Testing Conventions**

---

## Testing Requirements (MANDATORY)

**Policy:** ALL new features MUST include comprehensive Pest 4.0 tests

**Coverage types:**
- Feature tests (user workflows)
- Unit tests (services, models)
- Browser tests (critical UI flows)
- CLI command tests

**No exceptions:** Features without tests will NOT be merged

---

## Test Organization

### Directory Structure

```
tests/
├── Feature/                        # User-facing functionality
│   ├── Console/                    # CLI command tests
│   ├── Filament/                   # Filament resource tests
│   └── Services/                   # Service integration tests
├── Unit/                           # Isolated unit tests
│   ├── Models/                     # Model tests
│   ├── Services/                   # Service logic tests
│   └── Actions/                    # Action tests
└── Browser/                        # Pest v4 browser tests
```

**Package tests:** `packages/*/tests/` (same structure)

---

## Pest 4.0 Conventions

### Test Structure

```php
<?php

use App\Models\FleetVHost;
use App\Models\FleetVNode;

beforeEach(function () {
    // Setup runs before EACH test
    $this->vnode = FleetVNode::factory()->create();
});

describe('FleetVHost Model', function () {
    describe('relationships', function () {
        it('belongs to vnode', function () {
            $vhost = FleetVHost::factory()->create([
                'fleet_vnode_id' => $this->vnode->id
            ]);

            expect($vhost->vnode)->toBeInstanceOf(FleetVNode::class);
        });
    });

    describe('scopes', function () {
        it('filters active vhosts', function () {
            FleetVHost::factory()->create(['status' => 'active']);
            FleetVHost::factory()->create(['status' => 'suspended']);

            expect(FleetVHost::active()->count())->toBe(1);
        });
    });
});
```

### Assertions

```php
// ✅ Pest expect() style (preferred)
expect($vhost->status)->toBe('active');
expect($vhost->vconfs)->toHaveCount(54);
expect(fn() => $service->provision($vhost))
    ->toThrow(ProvisioningException::class);

// ✅ PHPUnit style (also valid)
$this->assertDatabaseHas(VConf::class, [
    'fleet_vhost_id' => $vhost->id,
    'name' => 'WPATH',
]);
```

---

## Critical Testing Rules

### 1. ALWAYS Mock SSH

```php
// ✅ CORRECT: Mock RemoteExecutionService
use NetServa\Cli\Services\RemoteExecutionService;

beforeEach(function () {
    RemoteExecutionService::fake([
        'markc' => RemoteExecutionService::fakeSuccess('Success message'),
    ]);
});

// ❌ WRONG: Real SSH in tests (slow, unreliable, dangerous)
```

### 2. Use Factories for Test Data

```php
// ✅ CORRECT: Use factories
$vhost = FleetVHost::factory()->create(['status' => 'active']);

// ❌ WRONG: Manual creation (brittle)
$vhost = FleetVHost::create([
    'fleet_vnode_id' => 1,
    'domain' => 'example.com',
    'status' => 'active',
    // ... 20 more fields
]);
```

### 3. Test Business Rules

```php
// Reference BR-### in test names and comments
test('enforces BR-001: unique domain per vnode', function () {
    $vnode = FleetVNode::factory()->create();

    FleetVHost::factory()->create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => 'example.com'
    ]);

    // BR-001: Duplicate domain on same vnode should fail
    expect(fn() => FleetVHost::factory()->create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => 'example.com'
    ]))->toThrow(DuplicateDomainException::class);
});
```

### 4. Test Both Success and Failure Paths

```php
describe('provision()', function () {
    it('successfully provisions vhost', function () {
        // Happy path
    });

    it('fails when vnode inactive', function () {
        // Error condition (BR-002)
    });

    it('rolls back on SSH failure', function () {
        // Failure scenario
    });
});
```

---

## Service Testing Pattern

```php
use App\Services\VHostProvisioningService;

beforeEach(function () {
    $this->service = app(VHostProvisioningService::class);
    $this->vnode = FleetVNode::factory()->create(['is_active' => true]);

    RemoteExecutionService::fake([
        $this->vnode->name => RemoteExecutionService::fakeSuccess('OK'),
    ]);
});

test('provisions vhost with all steps', function () {
    $vhost = FleetVHost::factory()->create([
        'fleet_vnode_id' => $this->vnode->id,
        'status' => 'pending'
    ]);

    $result = $this->service->provision($vhost);

    expect($result->success)->toBeTrue();
    expect($vhost->fresh()->status)->toBe('active');
    expect($vhost->vconfs)->toHaveCount(54);  // Initialized vconfs
});
```

---

## Filament Testing Pattern

```php
use App\Filament\Resources\FleetVHostResource\Pages\ListFleetVHosts;
use Livewire\Livewire;

test('can list vhosts', function () {
    $vhosts = FleetVHost::factory()->count(3)->create();

    livewire(ListFleetVHosts::class)
        ->assertCanSeeTableRecords($vhosts)
        ->searchTable($vhosts->first()->domain)
        ->assertCanSeeTableRecords($vhosts->take(1));
});

test('can create vhost', function () {
    $vnode = FleetVNode::factory()->create();

    livewire(CreateFleetVHost::class)
        ->fillForm([
            'fleet_vnode_id' => $vnode->id,
            'domain' => 'test.example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(FleetVHost::class, [
        'domain' => 'test.example.com',
    ]);
});
```

---

## Command Testing Pattern

```php
test('executes addvhost command', function () {
    RemoteExecutionService::fake([
        'markc' => RemoteExecutionService::fakeSuccess('VHost created'),
    ]);

    $vnode = FleetVNode::factory()->create(['name' => 'markc']);

    $this->artisan('addvhost', [
        'vnode' => 'markc',
        'vhost' => 'example.com',
        '--force' => true,
    ])
        ->assertSuccessful()
        ->expectsOutput('✅');

    $this->assertDatabaseHas(FleetVHost::class, [
        'domain' => 'example.com',
        'fleet_vnode_id' => $vnode->id,
    ]);
});
```

---

## Browser Testing (Pest v4)

```php
// tests/Browser/VHostProvisioningTest.php

test('can provision vhost via UI', function () {
    $vnode = FleetVNode::factory()->create(['name' => 'markc']);
    $vhost = FleetVHost::factory()->create([
        'fleet_vnode_id' => $vnode->id,
        'status' => 'pending'
    ]);

    $page = visit("/admin/fleet-vhosts/{$vhost->id}/edit");

    $page->assertSee('Provision VHost')
        ->click('Provision VHost')
        ->click('Confirm')  // Modal confirmation
        ->assertSee('provisioned successfully')
        ->assertNoJavascriptErrors();

    expect($vhost->fresh()->status)->toBe('active');
});
```

---

## Running Tests

```bash
# All tests
php artisan test

# Specific file
php artisan test tests/Feature/FleetVHostTest.php

# Filter by name
php artisan test --filter=provision

# With coverage
php artisan test --coverage --min=80

# Run twice (catch flaky tests)
php artisan test && php artisan test
```

---

## Common Mistakes

❌ **Don't test framework features:**
```php
// NO - testing Laravel's belongs to
test('belongs to vnode', function () {
    // Laravel relationships work, don't test them
});
```

✅ **Do test business logic:**
```php
// YES - testing business rule
test('requires active vnode (BR-002)', function () {
    // Test your business rule enforcement
});
```

❌ **Don't hit real servers:**
```php
// NO - real SSH in tests
$result = SSH::connect('192.168.1.227')->exec('ls');
```

✅ **Do mock external dependencies:**
```php
// YES - mocked SSH
RemoteExecutionService::fake([
    'markc' => RemoteExecutionService::fakeSuccess('OK'),
]);
```

---

**Complete testing guide:** `resources/docs/reference/testing_strategy.md`

**Version:** 1.0.0 (2025-10-08)
