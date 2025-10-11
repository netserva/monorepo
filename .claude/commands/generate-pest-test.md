# Generate Comprehensive Pest Test

Generate comprehensive Pest 4.0 tests for existing NetServa 3.0 code.

## Arguments

$ARGUMENTS should contain: `<ClassPath>` (e.g., `app/Services/VHostProvisioningService.php`)

## Task

Create comprehensive Pest 4.0 test coverage for **$ARGUMENTS**:

### 1. Analyze the Target

Read and understand the target file:
- Identify all public methods
- Note dependencies (services, models)
- Find business rules (BR-###) referenced
- Understand expected inputs/outputs
- Identify error conditions

### 2. Determine Test Type

Based on the class type, create appropriate test file:

**Services:** `tests/Unit/Services/<ServiceName>Test.php`
- Test all public methods
- Mock external dependencies (RemoteExecutionService, APIs)
- Test both success and failure paths
- Test business rule enforcement

**Actions:** `tests/Unit/Actions/<Domain>/<ActionName>Test.php`
- Test execute() method thoroughly
- Test validation rules
- Test database state changes
- Test exceptions thrown

**Models:** `tests/Unit/Models/<ModelName>Test.php`
- Test relationships
- Test scopes
- Test accessors/mutators
- Test business rule methods

**Commands:** `tests/Feature/Console/<CommandName>Test.php`
- Test command execution with various arguments
- Test output messages
- Test database changes
- Test SSH operations (mocked)

**Filament Resources:** `tests/Feature/Filament/<ResourceName>Test.php`
- Test list page
- Test create form
- Test edit form
- Test delete action
- Test custom actions
- Test filters

### 3. Test Structure

Use Pest 4.0 conventions:

```php
<?php

use App\Services\VHostProvisioningService;
use App\Models\FleetVHost;
use App\Models\FleetVNode;
use NetServa\Cli\Services\RemoteExecutionService;

beforeEach(function () {
    // Setup common test data
    $this->vnode = FleetVNode::factory()->create(['is_active' => true]);
    $this->vhost = FleetVHost::factory()->create([
        'fleet_vnode_id' => $this->vnode->id,
        'status' => 'pending'
    ]);
});

describe('VHostProvisioningService', function () {
    describe('provision()', function () {
        it('successfully provisions active vhost', function () {
            // Test implementation
        });

        it('throws exception for inactive vnode', function () {
            // Test BR-002: Active VNode Required
        });

        it('initializes vconfs during provisioning', function () {
            // Test vconf creation
        });

        it('rolls back on SSH failure', function () {
            // Test transaction rollback
        });
    });

    describe('verifyProvisioning()', function () {
        it('confirms nginx config is active', function () {
            // Test verification
        });
    });
});
```

### 4. Coverage Requirements

✅ **Happy Path:** All methods work with valid input
✅ **Edge Cases:** Empty strings, null values, boundary conditions
✅ **Error Paths:** Invalid input, exceptions, failures
✅ **Business Rules:** All BR-### rules enforced correctly
✅ **Database State:** Verify expected database changes
✅ **Mocking:** Mock SSH, external APIs, slow operations

### 5. Mocking Patterns

**RemoteExecutionService:**
```php
RemoteExecutionService::fake([
    'markc' => RemoteExecutionService::fakeSuccess('Success message'),
]);
```

**External APIs:**
```php
Http::fake([
    'api.example.com/*' => Http::response(['status' => 'ok'], 200),
]);
```

### 6. Assertions

Use expressive Pest assertions:
```php
expect($vhost->status)->toBe('active');
expect($vhost->vconfs)->toHaveCount(54);
expect(fn() => $service->provision($vhost))->toThrow(ProvisioningException::class);

$this->assertDatabaseHas(VConf::class, [
    'fleet_vhost_id' => $vhost->id,
    'name' => 'WPATH',
]);
```

### 7. Test Execution

After generating tests:
```bash
# Run new tests
php artisan test --filter=<TestName>

# Check coverage
php artisan test --coverage --min=80

# Run multiple times (catch flaky tests)
php artisan test --filter=<TestName> && php artisan test --filter=<TestName>
```

## Requirements

- ✅ Comprehensive coverage (happy paths, edge cases, errors)
- ✅ Mock SSH operations (NEVER hit real servers in tests)
- ✅ Test business rule enforcement (reference BR-### in comments)
- ✅ Use factories for test data
- ✅ Use descriptive test names ("it successfully provisions vhost")
- ✅ Test database state changes with assertions
- ✅ Include both positive and negative test cases

## Example Usage

```
claude /generate-pest-test app/Services/VHostProvisioningService.php
```

This will analyze the service and create comprehensive Pest tests.

## Notes

- Reference existing tests in `tests/` for patterns
- Use `RefreshDatabase` trait for database tests
- Use datasets for parameterized testing when appropriate
- Test one thing per test (focused assertions)
- Group related tests with `describe()` blocks
