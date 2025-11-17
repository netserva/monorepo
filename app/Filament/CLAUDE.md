# Filament Admin Panel - Context

**NetServa 3.0 Filament 4.1 Admin Panel Conventions**

---

## Filament v4.1 Specifics

**Version:** Filament 4.1 (NOT v3 - ignore v3 patterns)

**Key Changes from v3:**
- Schemas in `Schemas/Components/` (NOT Forms/Components/)
- All actions extend `Filament\Actions\Action` (NO Tables\Actions)
- File visibility private by default
- Filters deferred by default (use `deferFilters(false)` for immediate)
- Grid/Section no longer span all columns automatically

---

## Resource Organization

### Directory Structure

```
app/Filament/Resources/
├── FleetVhostResource.php          # Main resource
│   ├── Pages/
│   │   ├── CreateFleetVhost.php
│   │   ├── EditFleetVhost.php
│   │   └── ListFleetVhosts.php
│   ├── Schemas/                    # Filament v4: Form/table schemas
│   │   ├── FleetVhostFormSchema.php
│   │   └── FleetVhostTableSchema.php
│   ├── Actions/                    # Custom resource actions
│   │   ├── ProvisionVhostAction.php
│   │   └── FixPermissionsAction.php
│   └── RelationManagers/
│       └── VconfsRelationManager.php
```

**Pattern:** Separate schema classes for maintainability (Filament v4.1 best practice)

---

## Coding Conventions

### Form Schemas

```php
// ✅ Filament v4: Use Schemas namespace
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;

// ❌ v3 pattern - DO NOT USE
use Filament\Forms\Components\Section;
```

### Actions

```php
// ✅ Filament v4: All actions extend Filament\Actions\Action
use Filament\Actions\Action;

Action::make('provision')
    ->action(fn() => /* ... */);

// ❌ v3 - DO NOT USE
use Filament\Tables\Actions\Action;  // Doesn't exist in v4
```

### Filters

```php
// Filament v4: Filters deferred by default (click Apply button)
// For immediate application:
SelectFilter::make('status')
    ->deferFilters(false);  // Apply on change
```

---

## Business Logic Integration

**CRITICAL:** Filament resources are PRESENTATION LAYER ONLY

**DO in resources:**
- Form field definitions
- Table column definitions
- Filters and actions UI
- Validation rules (via form requests)
- Authorization (via policies)

**DO NOT in resources:**
- Business logic (use services)
- SSH execution (use RemoteExecutionService)
- Complex database queries (use repositories/services)
- File operations (use services)

**Pattern:**
```php
// ✅ CORRECT: Resource calls service
public function getHeaderActions(): array
{
    return [
        Action::make('provision')
            ->action(fn() => app(VhostProvisioningService::class)->provision($this->record))
    ];
}

// ❌ WRONG: Business logic in resource
public function getHeaderActions(): array
{
    return [
        Action::make('provision')
            ->action(function () {
                $this->record->update(['status' => 'provisioning']);
                // SSH commands inline... NO!
            })
    ];
}
```

---

## Testing Filament Resources

**Use Livewire assertions:**

```php
use Livewire\Livewire;

test('can list vhosts', function () {
    $vhosts = FleetVhost::factory()->count(3)->create();

    livewire(ListFleetVhosts::class)
        ->assertCanSeeTableRecords($vhosts);
});

test('can create vhost', function () {
    livewire(CreateFleetVhost::class)
        ->fillForm([
            'fleet_vnode_id' => $vnode->id,
            'domain' => 'example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();
});
```

**Always mock SSH:**
```php
RemoteExecutionService::fake([
    'markc' => RemoteExecutionService::fakeSuccess('Success'),
]);
```

---

## Authorization

**ALL resources MUST have policies:**

```php
// Resource
protected static ?string $policy = FleetVhostPolicy::class;

// Policy methods required
viewAny, view, create, update, delete
```

**Test authorization:**
```php
test('requires authentication', function () {
    $this->get(FleetVhostResource::getUrl('index'))
        ->assertRedirect('/login');
});
```

---

## Common Patterns

### Relationship Select with Search

```php
Select::make('fleet_vnode_id')
    ->label('VNode (Server)')
    ->relationship('vnode', 'name')
    ->searchable()
    ->preload()
    ->required();
```

### Status Badge Column

```php
BadgeColumn::make('status')
    ->colors([
        'secondary' => 'pending',
        'warning' => 'provisioning',
        'success' => 'active',
        'danger' => ['suspended', 'failed'],
    ]);
```

### Custom Header Action

```php
protected function getHeaderActions(): array
{
    return [
        Action::make('provision')
            ->label('Provision Vhost')
            ->icon('heroicon-o-cog')
            ->requiresConfirmation()
            ->action(fn() => app(VhostProvisioningService::class)->provision($this->record))
            ->visible(fn() => $this->record->status === 'pending'),
    ];
}
```

---

## Documentation References

**ALWAYS use `search-docs` BEFORE implementing Filament features:**
```
search-docs ["resource table columns", "filament actions", "form validation"]
```

**Official docs:** `resources/docs/architecture/filament-organization.md`

---

**Version:** 1.0.0 (2025-10-08)
