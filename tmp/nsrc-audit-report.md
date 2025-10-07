# .nsrc.sh Bash Function Audit Report

## Summary

**Total Functions Audited:** 56
**Working Commands:** 10 (17.9%)
**Non-existent Commands:** 46 (82.1%)
**Fixed Command Names:** 2

## Working Functions (10)

These bash functions call artisan commands that exist and work correctly:

1. `ns()` - `php artisan ns` ✅ (fixed from `platform:ns`)
2. `bl()` - `php artisan bl` ✅
3. `shhost()` - `php artisan shhost` ✅ (fixed from `platform:shhost`)
4. `mkfilament()` - `php artisan make:filament-resource` ✅
5. `migratevhost()` - `php artisan migrate:vhost-configs` ✅
6. `migrateplatform()` - `php artisan migrate:platform-profiles` ✅
7. `deploy-netserva-dashboards()` - `php artisan grafana` ✅
8. `sshm()` - `$NSBIN/sshm` ✅ (fixed from `php artisan ssh:host`)
9. `chperms()` - `php artisan chperms` ✅
10. `chpw()` - `php artisan chpw` ✅

## Non-existent Commands Commented Out (46)

### Password Management (3)
- `addpw()` - `platform:addpw` ❌
- `delpw()` - `platform:delpw` ❌
- `shpw()` - `platform:shpw` ❌

### SSH Management (3)
- `shssh()` - `platform:shssh` ❌
- `sshconfig()` - `ssh:config` ❌
- `sshkey()` - `ssh:key` ❌
- `debug-ssh()` - `ssh:debug` ❌

### Mail Management (7)
- `shmail()` - `shmail` ❌
- `shuser()` - `shuser` ❌
- `cleanspam()` - `mail:cleanspam` ❌
- `dkim()` - `mail:dkim` ❌
- `postscreen-access()` - `mail:postscreen` ❌
- `delvmail()` - `delvmail` ❌

### DNS/CloudFlare Management (11)
- `dnszones()` - `dns:cloudflare:zones` ❌
- `dnsrecords()` - `dns:cloudflare:records` ❌
- `dnssec()` - `dns:dnssec` ❌
- `cf-add-srv()` - `dns:cloudflare:records create` ❌
- `cf-create-simple()` - `dns:cloudflare:zones create` ❌
- `cf-fix-srv()` - `dns:cloudflare:records update` ❌
- `cf-test()` - `dns:cloudflare:zones list` ❌
- `migrate-zone()` - `dns:migrate-zone` ❌
- `enable-dnssec()` - `dns:dnssec enable` ❌
- `dnssec-add-ds()` - `dns:dnssec add-ds` ❌
- `dnssec-list-ds()` - `dns:dnssec list-ds` ❌
- `dnssec-remove-ds()` - `dns:dnssec remove-ds` ❌
- `dnssec-sync()` - `dns:dnssec sync` ❌
- `update-nameservers()` - `dns:update-nameservers` ❌
- `show-lan-zones()` - `dns:lan-zones` ❌
- `add-lan-zone()` - `dns:lan-zones add` ❌
- `monitor-dnssec-goldcoast()` - `monitor:dnssec` ❌

### SSL/TLS Management (4)
- `cert-reload()` - `ssl:reload` ❌
- `tls-audit-report()` - `ssl:audit` ❌
- `tls-security-check()` - `ssl:security-check` ❌
- `tls-quick-check()` - `ssl:quick-check` ❌

### Platform Management (4)
- `migrate()` - `platform:migrate` ❌
- `setup()` - `platform:setup` ❌
- `remove()` - `platform:remove` ❌
- `platformstatus()` - `platform:status` ❌

### Container Management (4)
- `chct()` - `container:change` ❌
- `container-logs()` - `container:logs` ❌
- `container-status()` - `container:status` ❌
- `incus-stats()` - `container:stats` ❌

### Backup Management (1)
- `backup-netserva()` - `backup:netserva` ❌

### Utility Functions (4)
- `systemstatus()` - `system:status` ❌
- `remote-exec()` - `platform:remote-exec` ❌
- `tofu()` - `platform:tofu` ❌

### Microsoft IP Tools (3)
- `update_ms_ips()` - `platform:update-ms-ips` ❌
- `update_ms_ips_batch()` - `platform:update-ms-ips --batch` ❌
- `show_ms_ips()` - `platform:show-ms-ips` ❌

### Proxmox/Incus Tools (1)
- `incus2proxmox()` - `platform:incus2proxmox` ❌

### Compatibility Wrappers (1)
- `install-compatibility-wrappers()` - `platform:install-wrappers` ❌

### VHost Management (1)
- `shwho()` - `shwho` ❌

## Recommendations

### High Priority Commands to Implement (Core Infrastructure)

1. **SSH Management** (netserva-ssh package):
   - `ssh:config` - Show/edit SSH configurations
   - `ssh:key` - Manage SSH keys
   - `ssh:debug` - Debug SSH connections

2. **DNS Management** (netserva-dns package):
   - `dns:cloudflare:zones` - Manage CloudFlare zones
   - `dns:cloudflare:records` - Manage CloudFlare DNS records
   - `dns:dnssec` - DNSSEC management commands

3. **SSL/TLS Management** (netserva-ssl package):
   - `ssl:reload` - Reload SSL certificates
   - `ssl:audit` - Audit SSL certificate status
   - `ssl:security-check` - Check SSL security configuration

4. **Password Management** (netserva-cli package):
   - `platform:addpw` - Add user password
   - `platform:delpw` - Delete user password
   - `platform:shpw` - Show password info

### Medium Priority Commands (Extended Functionality)

5. **Container Management** (netserva-fleet package):
   - `container:change` - Change container settings
   - `container:logs` - View container logs
   - `container:status` - Check container status

6. **Mail Management** (netserva-mail package):
   - `mail:cleanspam` - Clean spam
   - `mail:dkim` - DKIM management
   - `mail:postscreen` - Postscreen configuration

### Low Priority Commands (Nice to Have)

7. **Backup Management** (netserva-backup package):
   - `backup:netserva` - Backup NetServa infrastructure

8. **Monitoring** (netserva-monitor package):
   - `monitor:dnssec` - Monitor DNSSEC status
   - `system:status` - System status checks

9. **Platform Management** (netserva-cli package):
   - `platform:migrate` - Migration tools
   - `platform:setup` - Setup commands
   - `platform:remote-exec` - Remote execution
   - `platform:tofu` - OpenTofu/Terraform operations

### Commands That May Not Be Needed

These specialized commands may not be worth implementing:

- Microsoft IP tools (unless heavily used)
- `incus2proxmox` migration (one-time operation)
- `install-compatibility-wrappers` (legacy support)

## Files Modified

- `~/.ns/.nsrc.sh` - All non-existent commands commented out with `# TODO: Command doesn't exist`

## Verification Scripts Created

- `/tmp/check-nsrc-functions.sh` - Extract function definitions
- `/tmp/verify-artisan-commands.sh` - Verify command existence
- `/tmp/check-remaining-commands.sh` - Check specific commands

## Next Steps

1. Review this report with the user to prioritize which commands to implement
2. Create the high-priority commands first (SSH, DNS, SSL/TLS)
3. Consider creating these as proper Laravel Artisan commands in their respective packages
4. Update `.nsrc.sh` to uncomment functions as commands are implemented
5. Add tests for all newly implemented commands
