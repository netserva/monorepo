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
5. **Service Control**: ALWAYS use `sc()` function for service management - works across Alpine/Debian/OpenWrt
6. **Laravel Boost**: ALWAYS use `search-docs` MCP tool before implementing Laravel ecosystem features
7. **Testing**: ALL new features MUST include comprehensive Pest 4.0 tests in `packages/*/tests/`
8. **Platform Schema**: 6 layers - `venue → vsite → vnode → vhost + vconf → vserv`

---

## 🎯 Technology Stack

Laravel 12 + Filament 4.0 + Pest 4.0 + Laravel Prompts + phpseclib 3.x | SQLite (dev) / MySQL (prod)

---

## 📝 Essential Commands

```bash
# Laravel/Testing
php artisan fleet:discover --vnode=markc
php artisan test --filter=TestName
vendor/bin/pint --dirty

# Remote Shell Commands (ALWAYS use sx)
sx <vnode> <command> [args...]        # Interactive shell with full alias/function support

# Common Examples (NOTE: Quote functions/aliases to prevent local execution!)
sx nsorg u                            # Update packages (simple alias, no quotes needed)
sx nsorg l                            # View logs (simple alias)
sx nsorg i nginx                      # Install package (simple alias)
sx nsorg 'sc reload postfix'          # Reload service (MUST QUOTE - function!)
sx nsorg 'sc status nginx'            # Check service status (MUST QUOTE)
sx nsorg 'sc restart dovecot'         # Restart service (MUST QUOTE)

# CRITICAL: Why You Must Quote Functions
# When calling shell functions like sc(), ALWAYS use quotes:
#   ✅ CORRECT: sx gw 'sc status nginx'   (quotes prevent local sc() execution)
#   ❌ WRONG:   sx gw sc status nginx     (local sc() runs first, breaks command)

# Why sx is Better Than ssh:
# ✅ All aliases available (u, l, i, r, s)
# ✅ All functions available (sc, etc) when quoted
# ✅ Works with interactive commands
# ✅ Cleaner output (filters terminal warnings)
```

---

## 🔧 Shell Configuration Workflow (CRITICAL)

### The ~/.rc/ Ecosystem
NetServa uses a centralized shell configuration system that syncs across all servers:

**Workflow:**
1. **Edit locally**: Add aliases/functions to `~/.rc/_shrc` on workstation
2. **Sync to remote**: Run `~/.rc/rcm sync <vnode>`
3. **Use immediately**: `sx <vnode> <new_alias_or_function>`

**Example:**
```bash
# 1. Add new function to ~/.rc/_shrc
vi ~/.rc/_shrc
# Add: myfunction() { echo "Hello from $HOSTNAME"; }

# 2. Sync to remote server
~/.rc/rcm sync nsorg

# 3. Use immediately on remote
sx nsorg myfunction
# Output: Hello from nsorg
```

### BASH_ENV Configuration
All remote servers MUST have `BASH_ENV` exported in `~/.bash_profile`:

```bash
# ~/.bash_profile on remote servers
export BASH_ENV=~/.rc/_shrc
```

**Why This Matters:**
- Enables `sx` to access all aliases and functions via interactive shell (`bash -ci`)
- Makes functions available for direct `ssh` commands (though `sx` is preferred)
- Required for `RemoteExecutionService` to use cross-platform functions

**Initial Setup for New Remote Servers:**
1. Sync shell config: `~/.rc/rcm sync <vnode>`
2. Add to remote's `~/.bash_profile`: `export BASH_ENV=~/.rc/_shrc`
3. Test: `sx <vnode> sc status nginx`

### Key Files
- **`~/.rc/_shrc`** - Master shell config (aliases, functions, OS detection)
- **`~/.rc/rcm`** - Sync tool to deploy _shrc to remote servers
- **`~/.myrc`** - Machine-local customizations (never synced)
- **`~/.bash_profile`** - Loads _shrc and sets BASH_ENV on remotes

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
