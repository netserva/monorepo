# NetServa 3.0 - Agent Guidelines

## Build/Lint/Test Commands

```bash
# Development
composer run dev                    # Start serve + queue + logs + vite
npm run dev                        # Frontend hot reload

# Code Quality
vendor/bin/pint --dirty            # Format staged changes only
vendor/bin/pint                     # Format all code

# Testing
php artisan test                    # Run all tests
php artisan test --filter=TestName  # Run single test
php artisan test tests/Feature/     # Run test directory
```

## Code Style Guidelines

### PHP
- Use PHP 8.4+ with explicit return types and type hints
- Constructor property promotion in `__construct()`
- Database-first: ALL config in `vconfs` table, NEVER files
- Use Eloquent relationships over raw queries, prevent N+1 problems
- Follow Laravel 12 streamlined structure (no Kernel.php, auto-register commands)

### Laravel/Filament
- Filament 4.0 ONLY - use `Schemas\Components` for layout components
- All actions extend `Filament\Actions\Action`
- Use `relationship()` method for form select options
- Test Filament with `livewire()` assertions

### Testing
- Pest 4.0 ONLY - comprehensive browser testing supported
- 100% test coverage required for new features
- Use factories for test data, check for custom states
- Browser tests in `tests/Browser/`, feature tests in `tests/Feature/`

### Naming & Structure
- Descriptive names: `isRegisteredForDiscounts`, not `discount()`
- Plugin namespace: `NetServa\PluginName\Models\ModelName`
- CLI commands: `<command> <vnode> <vhost> [options]` (positional args only)

### Critical Rules
- NEVER hardcode credentials - use vconfs table
- ALWAYS use `sc()` function for service management
- Remote execution via `RemoteExecutionService::executeScript()` heredoc
- SSH commands use `sx <vnode> <command>` with quotes for functions