# NetServa Scripts Staging Area

This directory serves as a **staging ground** for bash scripts that are:
- Too useful to keep in `/tmp` (would be lost on reboot)
- Not yet mature enough to convert to permanent Laravel commands
- Being evaluated for regular use

## Workflow

```
/tmp/*.sh
    ↓ (if useful after testing)
~/.ns/scripts/*.sh
    ↓ (if regularly used)
~/.ns/packages/*/src/Console/Commands/*.php (Laravel Prompts/Artisan)
```

## Directory Structure

- `dns/` - DNS management scripts (sync, zone management, etc.)
- `backup/` - Backup and restore scripts
- `deploy/` - Deployment and provisioning scripts
- `maintenance/` - System maintenance utilities

## Script Status

### DNS Scripts (`dns/`)

| Script | Status | Purpose | Next Action |
|--------|--------|---------|-------------|
| `sync-homelab-to-pdns.sh` | 🟡 Candidate | Sync Laravel homelab provider to gw PowerDNS | Convert to Laravel command if used regularly |
| `add-ipv6-reverse-zone.sh` | 🟢 One-off | Added IPv6 reverse zone (completed) | Archive or retire |

### Legend
- 🟢 **One-off**: Script served its purpose, can be archived/retired
- 🟡 **Candidate**: Being evaluated, may convert to Laravel command
- 🔴 **Active**: Regularly used, should be converted soon
- ✅ **Converted**: Migrated to Laravel command (remove script)

## Guidelines

1. **Name scripts descriptively**: Use kebab-case (e.g., `sync-homelab-to-pdns.sh`)
2. **Add comments**: Include purpose, usage, and dependencies at top of script
3. **Make executable**: `chmod +x script-name.sh`
4. **Document here**: Add entry to "Script Status" table above
5. **Convert when ready**: When script is used 3+ times, convert to Laravel command
6. **Clean up**: Remove scripts after conversion or archive if completed

## Conversion Process

When converting a script to a Laravel command:

1. Create new Artisan command in appropriate package
2. Port logic using Laravel Prompts for user interaction
3. Add proper error handling and validation
4. Write tests for the command
5. Update this README to mark script as ✅ **Converted**
6. Move old script to `scripts/archive/` or delete

## Version Control

- ✅ **DO** commit useful scripts to git
- ✅ **DO** commit this README with status updates
- ❌ **DON'T** commit personal/machine-specific scripts (use `*.local.sh` pattern)
- ❌ **DON'T** commit scripts with hardcoded secrets (use environment variables)

---

**Last Updated:** 2025-10-11
