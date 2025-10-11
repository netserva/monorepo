# NetServa 3.0 - Essential Claude Code Rules

**📚 COMPLETE DOCUMENTATION:** `resources/docs/NetServa_3.0_Coding_Style.md`

---

## 🔒 Directory Access Policy (CRITICAL)

**✅ ALLOWED:** `~/.ns/`, `~/.rc/` and `~/Dev/' only
**❌ FORBIDDEN:** All other `/home/markc/` directories require explicit user permission

---

## 🚨 Mandatory Architecture Rules

1. **Database-First**: ALL vhost config/credentials stored in `vconfs` table - NEVER in files
2. **Remote SSH**: ALL remote scripts MUST use `RemoteExecutionService::executeScript()` heredoc method
3. **CLI Arguments**: ALL commands use `<command> <vnode> <vhost> [options]` - NO --vnode/--shost flags
4. **Execution Pattern**: Commands run FROM workstation TO remote servers via SSH - NEVER copy scripts to remotes
5. **Laravel Boost**: ALWAYS use `search-docs` MCP tool before implementing Laravel ecosystem features
6. **Testing**: ALL new features MUST include comprehensive Pest 4.0 tests in `packages/*/tests/`
7. **Platform Schema**: 6 layers - `venue → vsite → vnode → vhost + vconf → vserv`

---

## 🎯 Technology Stack

Laravel 12 + Filament 4.0 + Pest 4.0 + Laravel Prompts + phpseclib 3.x | SQLite (dev) / MySQL (prod)

---

## 📝 Essential Commands

```bash
php artisan fleet:discover --vnode=markc
php artisan test --filter=TestName
vendor/bin/pint --dirty
```

---

## ❌ Do NOT

- Never hardcode credentials (use vconfs table)
- Never copy scripts to remote servers (execute from workstation)
- Never use file-based config (use database)
- Never skip tests (100% coverage required)
- Never use Filament v3 syntax (ALWAYS use v4)

---

## 📚 Documentation References

- Architecture: `resources/docs/SSH_EXECUTION_ARCHITECTURE.md`
- VHost Variables: `resources/docs/VHOST-VARIABLES.md`
- AI Workflows: `resources/docs/ai/proven-workflows.md`
- Testing Strategy: `resources/docs/reference/testing_strategy.md`
