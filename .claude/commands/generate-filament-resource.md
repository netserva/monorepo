# Generate Filament Resource

Generate a complete Filament 4.1 resource with all necessary components for NetServa 3.0.

## Arguments

$ARGUMENTS should contain: `<ModelName>` (e.g., `FleetVHost`, `VConf`)

## Task

Create a complete Filament 4.1 resource for **$ARGUMENTS** model with NetServa conventions:

### 1. Resource File
Create `app/Filament/Resources/${ARGUMENTS}Resource.php`:
- Use Filament v4 schema pattern (NOT Forms)
- Include navigation icon (heroicon-o-*)
- Set navigation group ("Infrastructure", "Configuration", etc.)
- Reference schema and table classes
- Include relation managers array
- Define pages array (index, create, edit)

### 2. Form Schema
Create `app/Filament/Resources/${ARGUMENTS}Resource/Schemas/${ARGUMENTS}FormSchema.php`:
- Use Filament v4: `Filament\Schemas\Components\*`
- Group fields in Sections
- Use Grid for multi-column layouts
- Add helpful helper text for complex fields
- Use relationship() for foreign keys with searchable/preload
- Follow NetServa field naming (domain, vnode, vhost conventions)

### 3. Table Schema
Create `app/Filament/Resources/${ARGUMENTS}Resource/Schemas/${ARGUMENTS}TableSchema.php`:
- Use TextColumn, BadgeColumn appropriately
- Make key columns searchable and sortable
- Add filters for common queries (status, vnode, etc.)
- Use `deferFilters(false)` for immediate filter application
- Include EditAction and DeleteAction
- Add custom actions if relevant (provision, configure, etc.)

### 4. Resource Pages
Create three page files:
- `Pages/Create${ARGUMENTS}.php` - CreateRecord pattern
- `Pages/Edit${ARGUMENTS}.php` - EditRecord with header actions
- `Pages/List${ARGUMENTS}s.php` - ListRecords (note plural)

### 5. Policy
Create `app/Policies/${ARGUMENTS}Policy.php`:
- Implement viewAny, view, create, update, delete methods
- Follow NetServa authorization patterns
- Return true for admin users, check permissions for others

### 6. Tests
Create `tests/Feature/Filament/${ARGUMENTS}ResourceTest.php`:
- Test list page shows records
- Test create form and submission
- Test edit form and update
- Test delete action
- Test custom actions if any
- Test filters and search
- Use `livewire()` assertions for Filament
- Mock RemoteExecutionService if resource triggers SSH

### Requirements

- ✅ Use Filament 4.1 patterns (Schemas, not Forms)
- ✅ Follow NetServa conventions from CLAUDE.md
- ✅ Include comprehensive Pest 4.0 tests
- ✅ Add authorization via Policy
- ✅ Document business rules in code comments
- ✅ Use RemoteExecutionService::executeScript() for any SSH operations
- ✅ Store all config in database (vconfs table if applicable)

### Checklist

After generating all files:
- [ ] Run: `php artisan test --filter=${ARGUMENTS}Resource`
- [ ] Run: `vendor/bin/pint --dirty`
- [ ] Verify resource appears in Filament navigation
- [ ] Test create/edit/delete in browser
- [ ] Check authorization works correctly

## Notes

- Reference existing resources like FleetVHostResource for patterns
- Use `search-docs` for Filament v4 specific features
- All actions extend `Filament\Actions\Action` (no Table\Actions)
- File visibility is private by default in Filament v4
- Schemas are in `Schemas/Components/` not `Forms/Components/`

## Example Usage

```
claude /generate-filament-resource VConf
```

This will create complete Filament resource for VConf model with all components.
