# NS Testing Strategy with Pest 4.0

## Testing Policy (MANDATORY)

**ALL new features, services, commands, and resources MUST include comprehensive Pest 4.0 tests.**

## Test Coverage Requirements

### Feature Tests
- All user-facing functionality
- Complete user workflows
- Plugin integration scenarios
- CLI command interactions
- Web interface operations

### Unit Tests
- All service methods and business logic
- Model relationships and scopes
- Validation rules and custom logic
- Utility functions and helpers

### Browser Tests
- Critical UI workflows using Pest 4.0 browser testing
- Form submissions and validations
- Navigation and user interactions
- JavaScript functionality verification

### API Tests
- All API endpoints (if applicable)
- Authentication and authorization
- Request/response validation
- Error handling scenarios

## Test Structure and Organization

### Directory Structure
```
tests/
├── Feature/                 # Application feature tests
├── Unit/                    # Unit tests for core functionality
├── Browser/                 # Pest 4.0 browser tests
└── Datasets/                # Shared test datasets

packages/plugin-name/tests/
├── Feature/                 # Plugin feature tests
├── Unit/                    # Plugin unit tests
└── Browser/                 # Plugin browser tests
```

### Naming Conventions
- Test files: `DescriptiveNameTest.php`
- Test methods: Descriptive sentences
- Test datasets: `descriptive_dataset.php`

## Testing Workflow

### 1. Test-Driven Development (TDD)
Encouraged workflow:
1. Write test expectations first
2. Run tests to see failures
3. Implement minimal code to pass
4. Refactor and improve
5. Repeat cycle

### 2. Development Testing
- Run tests frequently: `php artisan test --filter=TestName`
- Focus on specific test classes during development
- Use `--parallel` flag for faster execution
- Monitor test coverage continuously

### 3. Pre-Commit Testing
- Run full test suite: `php artisan test`
- Ensure all tests pass before commits
- Verify no broken functionality
- Check test coverage metrics

## Plugin Testing Patterns

### Service Testing
```php
test('plugin service performs expected action', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    
    $service = app(PluginService::class);
    $result = $service->performAction('test-host');
    
    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->success)->toBeTrue()
        ->and($result->message)->toContain('Success');
    
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plugin_action_performed',
        'user_id' => $user->id
    ]);
});
```

### SSH Mocking
```php
test('ssh operations work correctly', function () {
    $mockConnection = Mockery::mock(SshConnection::class);
    
    $this->mock(SshConnectionService::class)
        ->shouldReceive('getConnection')
        ->with('test-host')
        ->andReturn($mockConnection);
        
    $result = app(PluginService::class)->performAction('test-host');
    
    expect($result)->toBeInstanceOf(Result::class);
});
```

### Browser Testing
```php
test('user can configure plugin via web interface', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    
    $page = $this->visit('/admin/plugin-settings');
    
    $page->assertSee('Plugin Configuration')
        ->fill('api_key', 'test-key-123')
        ->click('Save Settings')
        ->assertSee('Settings saved successfully')
        ->assertNoJavascriptErrors();
});
```

### Filament Resource Testing
```php
test('can list plugin resources', function () {
    $resources = PluginModel::factory()->count(3)->create();
    
    livewire(ListPluginResources::class)
        ->assertCanSeeTableRecords($resources)
        ->searchTable($resources->first()->name)
        ->assertCanSeeTableRecords($resources->take(1))
        ->assertCanNotSeeTableRecords($resources->skip(1));
});

test('can create plugin resource', function () {
    livewire(CreatePluginResource::class)
        ->fillForm([
            'name' => 'Test Resource',
            'description' => 'Test Description',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(PluginModel::class, [
        'name' => 'Test Resource',
        'description' => 'Test Description',
    ]);
});
```

## Test Data Management

### Factories
Use Laravel factories for test data:
```php
// database/factories/PluginModelFactory.php
class PluginModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
    
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

### Datasets
Use Pest datasets for parameterized testing:
```php
// tests/Datasets/ValidationDataset.php
dataset('invalid_emails', [
    'empty' => '',
    'invalid_format' => 'not-an-email',
    'missing_domain' => 'test@',
    'missing_local' => '@example.com',
]);

test('validates email format', function (string $email) {
    $result = app(ValidationService::class)->validateEmail($email);
    expect($result)->toBeFalse();
})->with('invalid_emails');
```

## Performance Testing

### SSH Connection Mocking
Always mock SSH connections in tests to:
- Eliminate external dependencies
- Speed up test execution
- Ensure reliable test results
- Avoid network timeouts

### Database Testing
- Use `RefreshDatabase` trait for database tests
- Prefer factories over manual data creation
- Use transactions for faster cleanup
- Consider using SQLite for test database

## Integration Testing

### Cross-Plugin Testing
Test plugin interactions:
```php
test('secrets integration with ssh manager for key authentication', function () {
    $secret = Secret::factory()->create(['type' => 'ssh_key']);
    $sshHost = SshHost::factory()->create(['auth_secret_id' => $secret->id]);
    
    $connectionService = app(SshConnectionService::class);
    $result = $connectionService->testConnection($sshHost);
    
    expect($result->success)->toBeTrue();
});
```

### Command Testing
Test CLI commands:
```php
test('nsis migrate command works correctly', function () {
    $host = SshHost::factory()->create();
    
    $this->artisan('nsis:migrate', ['host' => $host->hostname])
        ->expectsOutput('Migration completed successfully')
        ->assertExitCode(0);
});
```

## Continuous Integration

### Test Execution
- Run tests in parallel for speed
- Use different database for CI
- Cache dependencies appropriately
- Report test coverage metrics

### Quality Gates
- All tests must pass
- Minimum test coverage threshold
- No critical code quality issues
- Performance benchmarks maintained

## Test Debugging

### Debugging Tools
- Use `dd()` and `dump()` for variable inspection
- `$this->withoutExceptionHandling()` for detailed errors
- Browser screenshots for visual debugging
- Test isolation for complex scenarios

### Common Issues
- Database state contamination
- External service dependencies
- Timing-related failures
- Mock configuration problems

## Best Practices Summary

1. **Write tests first** (TDD approach)
2. **Mock external dependencies** (SSH, APIs, etc.)
3. **Use descriptive test names** and assertions
4. **Keep tests focused** and isolated
5. **Maintain high coverage** across all test types
6. **Run tests frequently** during development
7. **Use factories and datasets** for test data
8. **Test both happy and error paths**
9. **Verify database state** in feature tests
10. **Test plugin interactions** comprehensively