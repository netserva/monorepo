# 2025-12-04 - Initial Setup: midiman.studio

## Summary

Complete setup of midiman.studio on mrn server including:
- VHost provisioning with full directory structure
- DNS zone with email infrastructure records
- DKIM key generation and OpenDKIM configuration
- SSL certificate (Let's Encrypt) for domain + subdomains
- Nginx configuration with WordPress/Laravel support
- Email mailboxes (grant@, test@)
- Professional music studio website

## Commands Used

```bash
# VHost creation
php artisan addvhost mrn midiman.studio

# DNS zone (via pdnsutil on ns1rn)
pdnsutil create-zone midiman.studio ns1.renta.net admin.renta.net
pdnsutil add-record midiman.studio @ A 203.25.132.7
pdnsutil add-record midiman.studio www A 203.25.132.7
pdnsutil add-record midiman.studio @ MX "10 mail.renta.net"
# ... additional DNS records

# DKIM generation (on mrn)
opendkim-genkey -b 2048 -d midiman.studio -D /etc/opendkim/keys/midiman.studio -s mail -v

# SSL certificate
acme.sh --issue -d midiman.studio -d www.midiman.studio -d autoconfig.midiman.studio -d autodiscover.midiman.studio -w /srv/mail.renta.net/web/app/public

# Email accounts
php artisan addvmail mrn grant@midiman.studio
php artisan addvmail mrn test@midiman.studio
```

## Credentials

| Service | Username | Password |
|---------|----------|----------|
| Email (grant@) | grant@midiman.studio | KniKZeX0oiFi |
| Email (test@) | test@midiman.studio | j0RzhLt4g5lW |
| System user | u1012 | (system managed) |

## Mail Server Settings (NS 3.0 Standard)

- **Incoming (IMAP):** mail.renta.net:993 (SSL/TLS)
- **Outgoing (SMTP):** mail.renta.net:465 (SSL/TLS)
- **Username:** Full email address
- **Password:** As above

> **Note:** NS 3.0 mandates ports 465/993 only. Port 587 (STARTTLS) is NOT used.

## Files Created/Modified

### On mrn server:
- `/srv/midiman.studio/` - VHost base directory
- `/srv/midiman.studio/web/app/public/index.html` - Website
- `/srv/midiman.studio/web/app/public/favicon*` - Favicon package
- `/srv/midiman.studio/msg/grant/` - Grant's mailbox
- `/etc/nginx/sites-enabled/midiman.studio` - Nginx config
- `/etc/php/8.4/fpm/pool.d/midiman.studio.conf` - PHP-FPM pool
- `/etc/opendkim/keys/midiman.studio/` - DKIM keys
- `/etc/ssl/midiman.studio/` - SSL certificates

### On workstation (bug fixes):
- `packages/netserva-mail/src/Services/VmailManagementService.php`
- `packages/netserva-mail/src/Services/DovecotPasswordService.php`
- `packages/netserva-core/src/Services/BashScriptBuilder.php`

## Testing Performed

- [x] DNS resolution (dig midiman.studio)
- [x] SSL certificate valid (https://midiman.studio)
- [x] Website loads correctly
- [x] Email send/receive (tested to Gmail, reply received)
- [x] IMAP authentication (doveadm auth test)
- [x] DKIM signing (checked email headers)

## Notes

- Website is static HTML, ready for future WordPress or Laravel installation
- Favicon package uploaded from ~/Downloads/favicon_io.zip
- autoconfig/autodiscover subdomains configured for email client auto-setup
