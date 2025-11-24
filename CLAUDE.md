# NetServa 3.0 - Essential Rules

**📚 Full docs:** `resources/docs/NetServa_3.0_Coding_Style.md`

## 🔒 Directory Access

**✅ ALLOWED:** `~/.ns/`, `~/.rc/`, `~/Dev/` only
**❌ FORBIDDEN:** All other `/home/markc/` dirs need permission

## 📚 Knowledge System: Agents vs Runbooks vs Journal

**Three-part system** in `~/.ns/.claude/`:

| Type | Location | Purpose | Visibility |
|------|----------|---------|------------|
| **Agent** | `agents/` | 🤖 Autonomous problem solver (AI prompt) | ❌ Private (has infra context) |
| **Runbook** | `runbooks/` | 📋 Step-by-step procedure (commands) | ❌ Private (exact server details) |
| **Journal** | `journal/` | 📖 Historical record (what happened) | ❌ Private (session history) |

**When to use:**
- **Agent**: Novel/complex problem → autonomous exploration → creates/updates runbook
- **Runbook**: Known procedure → copy/paste commands → 15min execution
- **Journal**: Session history → `/snapshot` → future reference

**All `.claude/` directories gitignored** (contain IPs, hostnames, infrastructure topology)

## 📦 Package Architecture (Check FIRST!)

**Foundation:** core (SSH/models), config (standalone config)
**Infrastructure:** fleet (orchestration), ipam (IP mgmt), dns (PowerDNS/Cloudflare), wg (VPN)
**Services:** mail (Postfix/Dovecot), web (Nginx/SSL), cms (standalone, uses config NOT core)
**Operations:** ops (monitoring/backups), cron (scheduling)
**UI:** admin (Filament), cli (Prompts), platform (all-in-one)

**Key Rules:**
- core = ALL SSH (never duplicate)
- cms = STANDALONE (config, not core)
- fleet = orchestration hub
- Check map before building features

---

## 🔧 DRY: Service Layer (250+ commands!)

**Business logic MUST live in Services - NEVER duplicate in Commands + Filament!**

**Pattern:** CLI/Filament → Service (single source of truth) → DB/SSH/validation

**What Goes Where:**
- **Service** (`src/Services/`): ALL business logic, validation, orchestration
- **Command** (`src/Console/Commands/`): Input gathering, call service (thin wrapper)
- **Filament** (`src/Filament/Resources/`): Form schema, call service
- **Model** (`src/Models/`): Relationships, scopes, accessors ONLY

**Checklist:**
1. Create service first: `{Package}Service.php`
2. CLI command calls service (thin wrapper)
3. Filament calls SAME service
4. Test service once, mock in CLI/Filament tests

**Rule:** Same code in Command + Filament? → Belongs in Service!

---

## 🔥 Filament v4 & Laravel (MANDATORY)

**ALWAYS use `search-docs` MCP tool FIRST before ANY Laravel/Filament code!**

**Filament v4 Process (NON-NEGOTIABLE):**
1. 🛑 STOP - no code yet
2. 🔍 SEARCH - `search-docs` with multiple queries
3. 📖 READ - review imports, method signatures, API changes
4. ✍️ WRITE - use exact patterns from docs
5. ✅ VERIFY - check existing resources in `packages/*/src/Filament/Resources/`

**v3→v4 Key Changes:**
- `Form` → `Schema` | `Tables\Actions\Action` → `Actions\Action`
- `form(Form $form)` → `form(Schema $schema)`
- `->schema([])` → `->components([])` (top level)
- `->actions([])` → `->recordActions([])` (table)
- `->bulkActions([])` → `->toolbarActions([])`
- `Forms\Components\` → `Filament\Schemas\Components\`

**Multiple fields to one DB column:**
- Use different field names (e.g., `value_string`, `value_integer`)
- NO `statePath()` (causes hydration conflicts)
- Use `mutateFormDataBeforeFill()/BeforeSave()` in pages
- Use `->mutateRecordDataUsing()/using()` in table actions

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

## 🎯 CRUD Naming (250+ commands!)

**Pattern:** `add<resource>` `sh<resource>` `ch<resource>` `del<resource>`

**Why:** Alphabetical grouping, muscle memory, predictable, concise

**Examples:**
- DNS: adddns, shdns, chdns, deldns | addzone, shzone, chzone, delzone | addrec, shrec, chrec, delrec
- Fleet: addvnode, shvnode, chvnode, delvnode | addvsite, shvsite, chvsite, delvsite
- Mail: addvmail, shvmail, chvmail, delvmail | addvalias, shvalias, chvalias, delvalias

**Non-CRUD exceptions:** `fleet:discover`, `mail:configure-dkim`, `ops:backup-now`, `dns:verify-fcrdns`

**Rule:** CRUD = add/sh/ch/del | Non-CRUD = descriptive:name

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

# Remote Shell (ALWAYS use sx)
sx <vnode> <command>              # Interactive shell w/ all aliases/functions
sx nsorg u                        # Simple alias (no quotes)
sx nsorg 'sc reload postfix'      # MUST QUOTE functions! (prevents local execution)

# SCP Between Remotes (from workstation)
scp -r source:/path dest:/path    # Auto server-to-server transfer
```

## 🔧 Shell Config (~/.rc/ ecosystem)

**Workflow:** Edit `~/.rc/_shrc` → `~/.rc/rcm sync <vnode>` → `sx <vnode> <new_cmd>`

**Remote Setup:**
1. `~/.rc/rcm sync <vnode>`
2. Add to remote `~/.bash_profile`: `export BASH_ENV=~/.rc/_shrc`
3. Test: `sx <vnode> sc status nginx`

**Files:**
- `~/.rc/_shrc` - Master config (synced to all servers)
- `~/.rc/rcm` - Sync tool
- `~/.myrc` - Local customizations (never synced)
- `~/.bash_profile` - Loads config on remotes

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

## Searching Documentation
- Use `search-docs` tool for all Laravel ecosystem packages (Laravel, Inertia, Livewire, Filament, Tailwind, Pest, etc)
- Search BEFORE making code changes
- Use multiple, broad, topic-based queries: `['rate limiting', 'routing']`
- NO package names in queries (auto-included): `test resource table` NOT `filament 4 test resource table`
- Syntax: word searches, "exact phrases", multiple queries


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

### Filament Core Features
- Actions, Forms, Infolists (read-only), Notifications, Panels, Resources (CRUD), Schemas, Tables, Widgets
- Use `->relationship('author')` on form components for select/checkbox/repeater

## Testing
- Authenticate in tests
- Use `livewire()` or `Livewire::test()` for assertions
- Examples: `->assertCanSeeTableRecords()`, `->fillForm()`, `->call('create')`, `->callAction('send')`


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

## Laravel Essentials

- Use `php artisan make:` for files (migrations, controllers, models), pass `--no-interaction`
- **Database**: Use Eloquent relationships, avoid `DB::`, prevent N+1 with eager loading
- **Models**: Create factories/seeders too
- **APIs**: Use Eloquent API Resources + versioning
- **Validation**: Form Request classes (not inline), check sibling files for array vs string rules
- **Queues**: `ShouldQueue` interface for time-consuming ops
- **Auth**: Use gates, policies, Sanctum
- **URLs**: Named routes with `route()` function
- **Config**: `config('app.name')` NOT `env('APP_NAME')` (only in config files)
- **Testing**: Use factories, `$this->faker` or `fake()`, `make:test --pest`, most = feature tests
- **Vite error**: `npm run build` or `npm run dev`


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
- `php artisan make:livewire [Posts\CreatePost]`
- State on server, UI reflects it | Always validate/authorize
- Single root element | `wire:key` in loops | `wire:loading`/`wire:dirty` for states
- Lifecycle hooks: `mount()`, `updatedFoo()`
- Test: `Livewire::test(Counter::class)->assertSet('count', 0)->call('increment')`

## Livewire 3
- `wire:model.live` (real-time) | `wire:model` (deferred default)
- `App\Livewire` namespace | `$this->dispatch()` (not `emit`)
- New: `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target`
- Alpine included (plugins: persist, intersect, collapse, focus)
- Hook `livewire:init` | Check `fail.status === 419` for session expiry


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest
- `php artisan make:test --pest <name>` | Never remove tests without approval
- Test happy/failure/weird paths | `tests/Feature` & `tests/Unit`
- Syntax: `it('is true', function () { expect(true)->toBeTrue(); });`
- Run minimal tests: `php artisan test --filter=testName`
- Assertions: Use specific methods (`assertForbidden`, `assertNotFound`) not `assertStatus(403)`
- Mocking: `use function Pest\Laravel\mock;` or `$this->mock()`
- Datasets: Use `->with([])` for validation rule tests

## Pest 4
- Browser testing, smoke testing, visual regression, test sharding
- Browser tests in `tests/Browser/`
- Use Laravel features: `Event::fake()`, `assertAuthenticated()`, factories, `RefreshDatabase`
- Interact: click, type, scroll, select, submit, drag-and-drop
- Test multiple browsers/devices/viewports when requested
- Example: `visit('/sign-in')->assertSee('Sign In')->click('Forgot Password?')->fill('email', ...)`


=== tailwindcss/core rules ===

## Tailwind
- Check existing conventions | Extract repeated patterns to components
- Think through class placement, remove redundant classes, group logically
- **Spacing**: Use `gap` utilities, not margins
- **Dark mode**: Support if existing pages do (use `dark:`)

## Tailwind 4
- Use v4 only (not v3)
- Import: `@import "tailwindcss";` (NOT `@tailwind base/components/utilities;`)
- NO `corePlugins`
- **Replaced utilities**: `bg-opacity-*` → `bg-black/*`, `flex-shrink-*` → `shrink-*`, `flex-grow-*` → `grow-*`, `overflow-ellipsis` → `text-ellipsis`


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
