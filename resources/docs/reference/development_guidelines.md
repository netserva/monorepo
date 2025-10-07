# NS Development Guidelines

## Technology Stack

**Core Framework:**
- Laravel 12.24.0 (latest stable)
- Filament 4.0 (stable)
- Pest 4.0 (testing framework)
- Laravel Prompts (CLI interactions)
- phpseclib 3.x (SSH connections)

**Database:**
- SQLite (development/single-server)
- MySQL/MariaDB (production/multi-server)

## Laravel Boost MCP Tools (CRITICAL)

Always use Laravel Boost MCP tools for development:

**Essential Tools:**
- `search-docs` - ALWAYS use before implementing Laravel ecosystem features
- `tinker` - PHP debugging in Laravel context
- `database-schema` - Check schema before creating migrations
- `browser-logs` - Frontend debugging
- `database-query` - Read-only SQL queries
- `list-routes` - Verify routes before adding new ones
- `list-artisan-commands` - Check available commands

## Code Standards

### PHP Standards
- Use PHP 8 constructor property promotion
- Always use explicit return type declarations
- Use curly braces for all control structures
- Follow PSR-12 coding standards
- Run `vendor/bin/pint --dirty` before commits

### Laravel Patterns
- Use `php artisan make:` commands for file creation
- Prefer Eloquent relationships over raw queries
- Use Form Request classes for validation
- Use queued jobs for time-consuming operations
- Environment variables only in config files

### Filament 4.0 Patterns
- Always use `search-docs` for Filament documentation
- Use stable Filament 4.0 patterns (not beta)
- Leverage Livewire components for interactivity
- Follow auto-discovery patterns for resources

## Testing Requirements (MANDATORY)

**Policy:** ALL new features, services, commands, and resources MUST include comprehensive Pest 4.0 tests.

**Test Types:**
- Feature tests for user-facing functionality
- Unit tests for service methods and model logic
- Browser tests for critical UI workflows
- API tests for endpoints

**Workflow:**
1. TDD approach encouraged
2. Run tests frequently during development
3. Ensure 100% test coverage for new code
4. Run full test suite before commits

## Plugin Development

### Service Provider Pattern
All plugins extend `BaseNsisServiceProvider` for:
- Automatic migration loading
- Asset management
- Command registration
- Route loading
- Factory registration

### Plugin Interface Implementation
All plugins implement `NsisPluginInterface` providing:
- Unique identification
- Dependency management
- Enable/disable functionality
- Resource registration

### CLI + Web Integration
Design services to work in both contexts:
- Business logic in service classes
- CLI commands use services
- Filament resources use same services
- Consistent result objects

### Auto-Discovery
- Resources automatically discovered from `src/Filament/Resources/`
- Pages from `src/Filament/Pages/`
- Widgets from `src/Filament/Widgets/`
- Commands registered in service provider

## SSH Implementation

- Use phpseclib 3.x without SSH certificates
- Accept 50ms connection overhead for simplicity
- Focus on reliability over maximum optimization
- Implement connection pooling for performance

## File Organization

### Project Structure
```
packages/nsis-plugin-name/
├── src/                     # Source code
├── database/migrations/     # Database schema
├── tests/                   # Pest tests
├── config/                  # Configuration files
└── resources/               # Views, assets, translations
```

### Naming Conventions
- Plugin directories: `nsis-plugin-name`
- Service providers: `PluginNameServiceProvider`
- Commands: `nsis:plugin-action`
- Models: `PascalCase`
- Services: `PascalCaseService`
- Resources: `PascalCaseResource`

## Documentation Standards

### Documentation Files
- Use snake_case.md for all documentation files
- Project-wide docs in `/doc/`
- Plugin-specific docs in `packages/plugin-name/doc/`
- Include usage examples and API documentation

### Code Documentation
- PHPDoc blocks for all public methods
- Array shape type definitions
- Clear variable and method names
- Minimal inline comments

## Development Workflow

1. **Planning:** Use TodoWrite tool for task tracking
2. **Research:** Use `search-docs` before implementation
3. **Development:** TDD approach with frequent testing
4. **Testing:** Comprehensive Pest 4.0 test coverage
5. **Code Quality:** Run Pint formatter before commits
6. **Integration:** Verify auto-discovery works correctly

## Performance Considerations

- Plugin resources load only when enabled
- Cached plugin status checks
- Lazy-loading service registration
- Use Laravel queues for long operations
- Connection pooling for SSH operations