# Grant Houston - Client History

**Client:** Grant Houston
**Primary Domain:** midiman.studio
**Server:** mrn (BinaryLane Sydney)

---

## Projects

### 1. midiman.studio - Music Production Studio Website

**Date:** 2025-12-04

#### Infrastructure Setup

**VHost Provisioning:**
- Created vhost using `addvhost mrn midiman.studio`
- System user: `u1012` (UID: 1012, GID: 1012)
- Paths:
  - Base: `/srv/midiman.studio`
  - Web: `/srv/midiman.studio/web/app/public`
  - Mail: `/srv/midiman.studio/msg`

**DNS Zone (ns1rn PowerDNS):**
- Zone: `midiman.studio`
- Records created:
  - `midiman.studio` A → 203.25.132.7
  - `www.midiman.studio` A → 203.25.132.7
  - `autoconfig.midiman.studio` A → 203.25.132.7
  - `autodiscover.midiman.studio` A → 203.25.132.7
  - `midiman.studio` MX 10 → mail.renta.net
  - `midiman.studio` TXT (SPF) → "v=spf1 mx a include:mail.renta.net ~all"
  - `_dmarc.midiman.studio` TXT → "v=DMARC1; p=quarantine; rua=mailto:admin@midiman.studio"
  - `_autodiscover._tcp.midiman.studio` SRV → 0 1 443 mail.renta.net
  - `_imaps._tcp.midiman.studio` SRV → 0 1 993 mail.renta.net
  - `_submission._tcp.midiman.studio` SRV → 0 1 587 mail.renta.net
  - `mail._domainkey.midiman.studio` TXT (DKIM) → Generated key

**DKIM Setup (mrn):**
- Generated DKIM key with `opendkim-genkey`
- Key location: `/etc/opendkim/keys/midiman.studio/mail.private`
- Configured in:
  - `/etc/opendkim/KeyTable`
  - `/etc/opendkim/SigningTable`
  - `/etc/opendkim/TrustedHosts`

**SSL Certificate:**
- Issued via acme.sh/Let's Encrypt
- Domains: midiman.studio, www, autoconfig, autodiscover
- Certificate path: `/etc/ssl/midiman.studio/`
- Webroot validation via `/srv/mail.renta.net/web/app/public`

**Nginx Configuration:**
- HTTP → HTTPS redirect
- www → non-www redirect
- autoconfig/autodiscover subdomain handling
- SSL with HTTP/2
- WordPress security hardening (xmlrpc, wp-config, uploads blocked)
- Uses `/etc/nginx/common.conf` include

#### Email Setup

**Mailboxes Created:**
| Email | Password | Created |
|-------|----------|---------|
| grant@midiman.studio | KniKZeX0oiFi | 2025-12-04 |
| test@midiman.studio | j0RzhLt4g5lW | 2025-12-04 |

**Mail Configuration (NS 3.0 Standard):**
- IMAP: mail.renta.net:993 (SSL/TLS)
- SMTP: mail.renta.net:465 (SSL/TLS)
- Authentication: Email + Password

#### Website

**Type:** Static HTML (single-page)
**Theme:** Dark professional with gold accents
**Features:**
- Responsive design (mobile hamburger menu)
- Animated sound wave visualization
- Smooth scroll navigation
- Google Fonts (Montserrat, Playfair Display)

**Sections:**
1. Hero - Full viewport with animated sound waves
2. Services - 6 cards (Recording, Mixing, Mastering, Production, Composition, Sound Design)
3. About - Studio description with animated stats
4. Equipment - Professional gear showcase
5. Contact - Email link + social placeholders
6. Footer

**Files:**
- `/srv/midiman.studio/web/app/public/index.html`
- Favicon package (favicon.ico, apple-touch-icon.png, android-chrome-*.png, site.webmanifest)

---

## Bug Fixes During Setup

### addvmail Service Fixes

During this project, several bugs were discovered and fixed in the `VmailManagementService`:

1. **FleetVhost query** - Changed `fqdn` to `domain` column
2. **Database detection** - Added `getSqlCommand()` to detect MariaDB vs SQLite from vnode
3. **Column names** - Fixed INSERT to use `pass`, `home` (not `password`, `maildir`)
4. **UID/GID retrieval** - Added `getVhostFromRemote()` to fetch from remote vhosts table
5. **Password hash escaping** - Escape `$` characters in SHA512-CRYPT hash for bash heredoc
6. **Dovecot prefix** - Added `{SHA512-CRYPT}` prefix to password hashes

---

## Contact

**Grant Houston**
- Email: grant@midiman.studio
- Domain: midiman.studio
