# Spiderweb Mail Proxy Migration Plan

## Executive Summary

Migrate mail.spiderweb.com.au from direct hosting on sca (203.25.238.2) to proxy.renta.net (203.25.132.8), while keeping actual mailboxes on sca as backend. This frees up the hostname for future mailbox migration to mrn.

## Current State

### sca Server (mail.spiderweb.com.au)
- **IP**: 203.25.238.2
- **OS**: Ubuntu 24.04.3 LTS
- **Disk**: 97% full (1.5T/1.6T) - CRITICAL
- **Dovecot**: 2.3.21 with Pigeonhole
- **Hostname**: mail.spiderweb.com.au
- **Total domains**: ~160
- **Total mailboxes**: 673
- **Spiderweb mailboxes**: 463
- **Spiderweb mail storage**: 891GB
- **SSL**: Auto-renewed in /etc/ssl/mail.spiderweb.com.au/

### proxy.renta.net
- **IP**: 203.25.132.8
- **OS**: Debian 13 (trixie)
- **Dovecot**: 2.4.1 (proxy mode)
- **Currently proxying**: auwide.net → mail.renta.net / msg.renta.net
- **Database**: MySQL with vhosts/vmails/valias tables
- **SSL**: /etc/ssl/mail.auwide.net/

### DNS
- **spiderweb.com.au**: Hosted at Cloudflare
- **mail.spiderweb.com.au A**: 203.25.238.2
- **spiderweb.com.au MX**: 10 mail.spiderweb.com.au

---

## Step 1: Proxy Setup (No Downtime Migration)

### 1.1 Create Backend Hostname for sca

Since mail.spiderweb.com.au will point to the proxy, sca needs a different hostname for backend access.

**Option A**: Use existing IP directly (recommended for simplicity)
- Backend: `203.25.238.2` (no DNS needed)

**Option B**: Create backend-sca.renta.net
- Add A record: `backend-sca.renta.net → 203.25.238.2`

### 1.2 Obtain SSL Certificate on Proxy

```bash
# On proxy.renta.net
mkdir -p /etc/ssl/mail.spiderweb.com.au

# Option 1: Use certbot (requires DNS pointing to proxy first - chicken/egg)
# Option 2: Use DNS-01 challenge via Cloudflare API
# Option 3: Copy existing cert from sca temporarily

# For initial testing, copy from sca:
scp sca:/etc/ssl/mail.spiderweb.com.au/fullchain.pem /etc/ssl/mail.spiderweb.com.au/
scp sca:/etc/ssl/mail.spiderweb.com.au/privkey.pem /etc/ssl/mail.spiderweb.com.au/key.pem
```

### 1.3 Update Dovecot Config on Proxy

Edit `/etc/dovecot/dovecot.conf` to add spiderweb certificate:

```conf
# Add additional ssl_server block
ssl_server mail.spiderweb.com.au {
  ca_file = /etc/ssl/certs/ca-certificates.crt
  cert_file = /etc/ssl/mail.spiderweb.com.au/fullchain.pem
  key_file = /etc/ssl/mail.spiderweb.com.au/key.pem
}
```

### 1.4 Export Spiderweb Data from sca

```bash
# On sca - Export spiderweb domain
mysql -usysadm -pc9djhfFeHhRrbkuM sysadm -e "
SELECT 'spiderweb.com.au' as domain, 1000 as uid, 1000 as gid, 1 as active;
" > /tmp/spiderweb_vhosts.sql

# Export spiderweb mailboxes (adding backend column)
mysql -usysadm -pc9djhfFeHhRrbkuM sysadm -e "
SELECT user, pass, home, uid, gid, '203.25.238.2' as backend, active
FROM vmails
WHERE user LIKE '%@spiderweb.com.au';
" > /tmp/spiderweb_vmails.tsv

# Export spiderweb aliases
mysql -usysadm -pc9djhfFeHhRrbkuM sysadm -e "
SELECT source, target, active
FROM valias
WHERE source LIKE '%@spiderweb.com.au';
" > /tmp/spiderweb_valias.tsv
```

### 1.5 Import Data to Proxy

```bash
# On proxy.renta.net

# Add spiderweb.com.au domain
mysql -upostfix -p0ScVsM9FrLE6L6BkOGA8 sysadm -e "
INSERT INTO vhosts (domain, uid, gid, active)
VALUES ('spiderweb.com.au', 1000, 1000, 1);
"

# Import mailboxes (463 records) - need to transform TSV to INSERT
# The key is setting backend = '203.25.238.2' for proxy routing
```

**SQL Import Script** (create on proxy):
```sql
-- /tmp/import_spiderweb.sql
INSERT INTO vmails (user, pass, home, uid, gid, backend, active)
SELECT user, pass, home, uid, gid, '203.25.238.2', active
FROM (
  -- Data from sca export goes here
) AS import_data;
```

### 1.6 Update Postfix on Proxy

Postfix needs virtual alias and mailbox maps. Check existing config:

```bash
# Verify postfix-mysql configs handle spiderweb.com.au
postconf virtual_mailbox_domains virtual_alias_maps virtual_mailbox_maps
```

### 1.7 Test Before DNS Switch

```bash
# On proxy - verify Dovecot can proxy authenticate
# Add test entry first:
mysql -upostfix -p0ScVsM9FrLE6L6BkOGA8 sysadm -e "
INSERT INTO vmails (user, pass, home, uid, gid, backend, active)
VALUES ('testproxy@spiderweb.com.au',
        '{SHA512-CRYPT}\$6\$test...',
        '/home/u/spiderweb.com.au/home/testproxy',
        1000, 1000, '203.25.238.2', 1);
"

# Test IMAP connection directly to proxy
openssl s_client -connect 203.25.132.8:993 -servername mail.spiderweb.com.au
```

### 1.8 DNS Cutover (Cloudflare)

Once testing confirms proxy works:

```
# In Cloudflare DNS for spiderweb.com.au:
# Change: mail.spiderweb.com.au A 203.25.238.2
# To:     mail.spiderweb.com.au A 203.25.132.8
```

TTL is typically 300s at Cloudflare, so cutover is fast.

### 1.9 Verify Post-Cutover

```bash
# Check DNS propagation
dig +short mail.spiderweb.com.au

# Test IMAP login
openssl s_client -connect mail.spiderweb.com.au:993

# Check proxy logs
tail -f /var/log/dovecot.log
```

---

## Step 2: Mailbox Migration (Future - sca → mrn)

Once Step 1 is stable:

1. Create spiderweb maildir structure on mrn
2. rsync mailboxes: `rsync -avz sca:/home/u/spiderweb.com.au/home/ mrn:/home/u/spiderweb.com.au/home/`
3. Update proxy database: `backend = 'mrn.renta.net'` or mrn's IP
4. Final rsync with --delete
5. Verify and clean up

---

## Risk Assessment

| Risk | Mitigation |
|------|------------|
| SSL cert mismatch | Copy existing cert first, get new cert after DNS switch |
| Auth failure | Test with single mailbox before bulk import |
| Password format incompatibility | Both use SHA512-CRYPT, should be compatible |
| Postfix LMTP delivery | Proxy forwards to sca's LMTP, test inbound mail |
| DNS propagation delay | Cloudflare TTL is low (300s), minimal impact |

---

## Rollback Plan

If issues occur after DNS switch:
1. Revert DNS: mail.spiderweb.com.au A → 203.25.238.2
2. Wait for propagation (5-10 minutes)
3. Diagnose issue on proxy
4. Retry when fixed

---

## Pre-Migration Checklist

- [ ] Backup sca vmails/valias tables
- [ ] Copy SSL cert to proxy
- [ ] Update Dovecot config on proxy
- [ ] Import vhosts record to proxy
- [ ] Import vmails records (463) to proxy
- [ ] Import valias records to proxy
- [ ] Test single mailbox auth through proxy
- [ ] Test SMTP delivery through proxy
- [ ] Schedule maintenance window (optional - should be seamless)
- [ ] Update DNS in Cloudflare
- [ ] Verify all mailboxes accessible
- [ ] Monitor for 24 hours

---

## Commands Quick Reference

```bash
# sca MySQL
mysql -usysadm -pc9djhfFeHhRrbkuM sysadm

# proxy MySQL
mysql -upostfix -p0ScVsM9FrLE6L6BkOGA8 sysadm

# Test IMAP auth
openssl s_client -connect mail.spiderweb.com.au:993

# Dovecot logs
tail -f /var/log/dovecot.log

# Postfix logs
tail -f /var/log/mail.log
```
