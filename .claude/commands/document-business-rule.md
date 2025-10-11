# Document Business Rule

Add a new business rule to the NetServa 3.0 business rules catalog.

## Arguments

$ARGUMENTS should contain: `<short-title>` (e.g., "unique-domain-per-vnode", "active-vnode-required")

## Task

Create a comprehensive business rule entry for **$ARGUMENTS** in `docs/business-rules/`.

### 1. Determine Next BR Number

```bash
# Find highest existing BR number
ls docs/business-rules/*.md | grep -oE 'BR-[0-9]+' | sort -V | tail -1
# If BR-015, create BR-016
```

### 2. Create Business Rule File

Create `docs/business-rules/BR-###-${slug}.md`:

```markdown
# BR-###: ${Title}

**Domain:** ${Domain} (e.g., VHost Management, Authentication, Billing)

**Status:** Proposed | Active | Deprecated

**Date Added:** YYYY-MM-DD

---

## Rule Statement

One clear sentence stating the business rule.

Example: "Each domain can exist only once per vnode (server)"

---

## Rationale

Why does this rule exist? What problem does it solve?

Example:
- Prevents DNS conflicts (two vhosts with same domain)
- Avoids nginx server_name collisions
- Ensures clear ownership of domains per server

---

## Implementation

Where and how is this rule enforced?

### Database Level
- Table: ${table_name}
- Constraint: Unique index on (vnode_id, domain)
- Migration: `database/migrations/*_add_unique_domain_per_vnode.php`

### Application Level
- Model: `app/Models/${ModelName}.php:${line}`
- Validation: `app/Actions/${ActionName}.php:${line}`
- Service: `app/Services/${ServiceName}.php::${method}()`

### API Level
- Form Request: `app/Http/Requests/${RequestName}.php`
- Validation Rule: `domain unique:fleet_vhosts,domain,null,id,fleet_vnode_id,{$vnodeId}`

---

## Valid Scenarios

Provide 3-5 examples of valid scenarios:

✅ **Example 1:**
- VNode: `markc`
- Domain: `example.com`
- Result: OK (first occurrence)

✅ **Example 2:**
- VNode: `backup`
- Domain: `example.com`
- Result: OK (different vnode, allowed)

✅ **Example 3:**
- VNode: `markc`
- Domain: `test.example.com`
- Result: OK (different domain)

---

## Invalid Scenarios

Provide 3-5 examples of violations:

❌ **Example 1:**
- VNode: `markc`
- Domain: `example.com` (already exists)
- Error: `DuplicateDomainException: Domain example.com already exists on vnode markc`

❌ **Example 2:**
- Attempting to create via Filament admin panel
- Result: Validation error shown to user

❌ **Example 3:**
- CLI command: `php artisan addvhost markc example.com`
- Result: Command fails with error message

---

## Related Rules

List related business rules:

- BR-###: ${Related Rule Name}
- BR-###: ${Another Related Rule}

---

## Exceptions

Are there any cases where this rule doesn't apply?

Example:
- During migration from NetServa 2.0, duplicates may exist temporarily
- Soft-deleted vhosts don't count toward uniqueness (deleted_at IS NOT NULL)

---

## Testing

### Unit Tests
- File: `tests/Unit/Models/${ModelName}Test.php`
- Tests:
  - `it enforces unique domain per vnode`
  - `it allows same domain on different vnodes`

### Feature Tests
- File: `tests/Feature/${Domain}/${FeatureName}Test.php`
- Tests:
  - `it prevents duplicate domain creation via service`
  - `it shows validation error in Filament resource`

### Example Test:
```php
test('enforces unique domain per vnode', function () {
    $vnode = FleetVNode::factory()->create();

    FleetVHost::factory()->create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => 'example.com'
    ]);

    expect(fn() => FleetVHost::factory()->create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => 'example.com'
    ]))->toThrow(DuplicateDomainException::class);
});
```

---

## Code Examples

### Enforcement in Service Layer

```php
/**
 * Create VHost (enforces BR-###: Unique Domain Per VNode)
 */
public function createVHost(FleetVNode $vnode, string $domain): FleetVHost
{
    // BR-###: Check for duplicate domain
    if (FleetVHost::where('fleet_vnode_id', $vnode->id)
                   ->where('domain', $domain)
                   ->exists()) {
        throw new DuplicateDomainException(
            "Domain {$domain} already exists on vnode {$vnode->name}"
        );
    }

    return FleetVHost::create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => $domain,
        'status' => 'pending',
    ]);
}
```

### Database Migration

```php
Schema::create('fleet_vhosts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fleet_vnode_id')->constrained('fleet_vnodes');
    $table->string('domain');

    // BR-###: Unique domain per vnode
    $table->unique(['fleet_vnode_id', 'domain'], 'unique_domain_per_vnode');
});
```

---

## Change History

Track changes to this business rule:

- **2025-10-08:** Initial creation (BR-###)
- **YYYY-MM-DD:** Updated rationale to include ...
- **YYYY-MM-DD:** Added exception for soft-deleted vhosts

---

## References

- ADR-###: ${Related Architectural Decision}
- Documentation: `docs/${related-doc}.md`
- Model: `app/Models/${ModelName}.php`
- Service: `app/Services/${ServiceName}.php`

---

**Status:** ${Status}
**Last Updated:** YYYY-MM-DD
**Reviewed By:** ${Name}
```

### 3. Update Business Rules Index

Add to `docs/business-rules/README.md`:

```markdown
| BR-### | ${Title} | ${Status} | ${Date} |
```

### 4. Update CLAUDE.md

If this is a critical rule, add reference to root `CLAUDE.md`:

```markdown
## Business Rules
- BR-###: ${One-line summary}
```

### 5. Verification

After creating:
- [ ] Business rule is clear and actionable
- [ ] Valid and invalid examples provided
- [ ] Implementation locations documented
- [ ] Tests referenced or created
- [ ] Related rules linked
- [ ] Index updated

## Requirements

- ✅ Clear one-sentence rule statement
- ✅ Rationale explaining why rule exists
- ✅ Implementation details (where enforced)
- ✅ Valid scenarios (3-5 examples)
- ✅ Invalid scenarios (3-5 examples)
- ✅ Test references
- ✅ Code examples showing enforcement

## Example Usage

```
claude /document-business-rule unique-domain-per-vnode
```

This will create `docs/business-rules/BR-###-unique-domain-per-vnode.md`.

## Notes

- Create `docs/business-rules/` directory if it doesn't exist
- Business rules catalog is referenced by AI when generating code
- Keep rules concise but comprehensive
- Update rules when business logic changes (add to change history)
