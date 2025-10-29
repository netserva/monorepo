# NetServa 3.0 Config Templates

This directory contains static configuration file templates for NetServa 3.0 system services and applications.

## Purpose

These templates serve as the foundation for generating vhost-specific configuration files on remote servers. They are:

- **Plain static files** (no template syntax like Blade or shell variables)
- **Variable substitution** happens at runtime using data from the `vconfs` database table
- **Database-first approach** - all vhost configuration and credentials are stored in the database, never hardcoded
- **Public in GitHub** - safe to commit (no secrets, no credentials)

## Naming Convention

Config filenames use **underscore path encoding** to represent their target filesystem location:

### Rules

1. **Default path prefix**: `/etc/` (automatically prepended)
2. **Path separator**: `_` (underscore) represents `/` in filesystem paths
3. **Preserve extension**: Keep original file extension (`.conf`, `.cf`, `.sieve`, etc.)

### Examples

```
# Standard /etc/ configs (most common)
nginx_common.conf              → /etc/nginx/common.conf
nginx_sites-enabled_default    → /etc/nginx/sites-enabled/default
dovecot_dovecot.conf           → /etc/dovecot/dovecot.conf
dovecot_sieve_global.sieve     → /etc/dovecot/sieve/global.sieve
postfix_main.cf                → /etc/postfix/main.cf
postfix_mysql-alias-maps.cf    → /etc/postfix/mysql-alias-maps.cf
powerdns_pdns.conf             → /etc/powerdns/pdns.conf
sysctl.conf                    → /etc/sysctl.conf
sudoers.d_99-sysadm            → /etc/sudoers.d/99-sysadm

# Non-/etc/ paths (special prefixes)
hcp_opcache.php                → {vhost_path}/hcp/opcache.php
.ssh_config                    → ~/.ssh/config
usr_share_nginx_html_50x.html  → /usr/share/nginx/html/50x.html
.well-known_autodiscover.php   → {vhost_path}/.well-known/autodiscover.php
```

## Path Resolution Algorithm

The path resolution logic recognizes special prefixes to handle non-`/etc/` paths:

```php
function resolveConfigPath(string $filename): string {
    // VHost-relative paths
    if (str_starts_with($filename, 'hcp_')) {
        return '{vhost_path}/' . str_replace('_', '/', $filename);
    }

    // Absolute non-/etc/ paths
    if (str_starts_with($filename, 'usr_')) {
        return '/' . str_replace('_', '/', $filename);
    }

    // Home directory paths
    if (str_starts_with($filename, '.')) {
        return '~/' . str_replace('_', '/', $filename);
    }

    // Default: /etc/ prefix
    return '/etc/' . str_replace('_', '/', $filename);
}
```

## Variable Substitution

Templates are **plain static files** without embedded variable syntax. Variable substitution happens at runtime:

1. **Source of truth**: `vconfs` table in database (see `app/Models/Vconf.php`)
2. **Template rendering**: Variables replaced during vhost setup/deployment
3. **Available variables**: See `resources/docs/VHOST-VARIABLES.md` for complete list

### Example Workflow

```php
// 1. Read template from resources/configs/
$template = file_get_contents(resource_path('configs/nginx_common.conf'));

// 2. Get variables from vconfs table
$vars = Vconf::where('vhost_id', $vhostId)->pluck('value', 'key');

// 3. Replace variables in template
$rendered = strtr($template, [
    'DOMAIN' => $vars['domain'],
    'DOCRT' => $vars['docroot'],
    'PHPVR' => $vars['php_version'],
    // ... more variables
]);

// 4. Deploy to remote server via RemoteExecutionService
RemoteExecutionService::executeScript($vnode, "cat > /etc/nginx/common.conf << 'EOF'\n{$rendered}\nEOF");
```

## Adding New Templates

This directory is currently **empty by design**. Population will happen gradually as a separate sub-project.

### Process for Adding Templates

1. **Source identification**: Find original in NS 1.0 (`~/.shold/etc/`) or live servers
2. **Comparison**: Compare NS 1.0 version with current production configs on NS 3.0 servers
3. **Line-by-line review**: Vet every line for NS 3.0 compatibility
4. **Variable identification**: Identify which values need to come from `vconfs` table
5. **Testing**: Test template thoroughly on dev/staging before committing
6. **Documentation**: Update this README if new patterns emerge

### Sources for Templates

- **NS 1.0 originals**: `~/.shold/etc/` directory
- **Live NS 1.0 servers**: Production configs from legacy servers
- **Live NS 3.0 servers**: Current production configs (may have manual tweaks)
- **Service defaults**: Upstream default configs from packages

### Quality Standards

- No hardcoded credentials or secrets
- No server-specific paths (use variables)
- Cross-platform compatible (Alpine/Debian/OpenWrt where applicable)
- Well-commented for future maintainers
- Tested on at least one vhost type

## Architecture Integration

### Database-First Principle

NetServa 3.0 follows a **database-first architecture** where ALL vhost configuration is stored in the `vconfs` table. These templates are:

- **NOT** the source of truth (database is)
- **Default/skeleton** configurations that get customized per-vhost
- **Deployed once** during initial vhost setup, then managed via database

### Remote Execution Model

Configs are deployed using `RemoteExecutionService::executeScript()` which:

1. Runs FROM workstation TO remote servers
2. Executes scripts via SSH heredoc (never copies scripts to remote)
3. Uses cross-platform `sc()` function for service control
4. Leverages `~/.rc/_shrc` shell functions/aliases synced to all servers

See `resources/docs/SSH_EXECUTION_ARCHITECTURE.md` for details.

## Related Documentation

- **Architecture**: `resources/docs/SSH_EXECUTION_ARCHITECTURE.md`
- **VHost Variables**: `resources/docs/VHOST-VARIABLES.md`
- **Platform Schema**: `resources/docs/NetServa_3.0_Coding_Style.md`
- **NS 1.0 Reference**: `~/.shold/etc/` directory (original templates)

## Migration from NS 1.0

The original NS 1.0 templates used full path encoding with `/etc/` prefix:

```
# NS 1.0 naming
_etc_nginx_common.conf         → /etc/nginx/common.conf

# NS 3.0 naming (stripped /etc/ prefix)
nginx_common.conf              → /etc/nginx/common.conf
```

**Benefits of stripping `/etc/` prefix:**
- Cleaner, more readable filenames
- Natural service grouping when sorted alphabetically
- Shorter names while maintaining full path information
- 95% of configs are in `/etc/` anyway
