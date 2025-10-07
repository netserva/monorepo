# NetServa Configuration Templates

This directory contains configuration templates for various NetServa services. These templates are used by the Laravel application to generate service configurations during deployment.

## 📁 Template Organization

### Dovecot Mail Server Templates
- **`NetServa_dovecot.conf`** - Main Dovecot configuration template
- **`_etc_dovecot_dovecot.conf`** - Complete Dovecot configuration file
- **`_etc_dovecot_dovecot.conf-mysql`** - MySQL database configuration snippet
- **`_etc_dovecot_dovecot.conf-sqlite`** - SQLite database configuration snippet
- **`_etc_dovecot_sieve_global.sieve`** - Global Sieve filtering rules
- **`_etc_dovecot_sieve_retrain-as-good.sieve`** - Spam retraining rules
- **`_etc_dovecot_sieve_retrain-as-spam.sieve`** - Spam identification rules

### Web Server Templates
- **`_etc_nginx_nginx.conf`** - Main Nginx configuration template
- **`_etc_nginx_sites-available_vhost.conf`** - Virtual host configuration template
- **`_srv_vhost_nginx.conf`** - Per-vhost Nginx configuration

### Mail Server Components
- **`_etc_postfix_main.cf`** - Postfix main configuration template
- **`_etc_postfix_master.cf`** - Postfix master process configuration
- **`_etc_spamprobe_spamprobe.conf`** - SpamProbe spam filtering configuration

### SSL/TLS Templates
- **`_etc_ssl_example.com.conf`** - SSL certificate configuration template

### Application Templates
- **`_.well-known_autodiscover.php`** - Email autodiscovery for mail clients
- **`_cron_netserva`** - Cron job configuration for NetServa tasks

## 🔧 Usage in Laravel

Templates are accessed via Laravel's resource system:

```php
// Access a template
$template = resource_path('templates/NetServa_dovecot.conf');

// Using Laravel's File facade
use Illuminate\Support\Facades\File;
$content = File::get(resource_path('templates/_etc_nginx_nginx.conf'));

// With template variable substitution
$config = str_replace('{{DOMAIN}}', $domain, $template);
```

## 📝 Template Variables

Templates use the following variable substitution patterns:

### Common Variables
- `{{DOMAIN}}` - Primary domain name
- `{{VHOST}}` - Virtual host domain
- `{{VPATH}}` - Virtual host path (/srv)
- `{{WPATH}}` - Web path (/srv/domain/web)
- `{{MPATH}}` - Mail path (/srv/domain/msg)

### Database Variables
- `{{DHOST}}` - Database host
- `{{DNAME}}` - Database name
- `{{DUSER}}` - Database user
- `{{DPASS}}` - Database password
- `{{DTYPE}}` - Database type (mysql/sqlite)

### SSL Variables
- `{{SPATH}}` - SSL certificate path
- `{{CERT_PATH}}` - Certificate file path
- `{{KEY_PATH}}` - Private key file path

### User Variables
- `{{UUSER}}` - System user
- `{{UPATH}}` - User home path
- `{{ADMIN}}` - Administrator username
- `{{AMAIL}}` - Administrator email

## 🚀 Migration from cfg/

These templates were migrated from `~/.ns/cfg/` to integrate with Laravel's resource system. This provides:

- **Consistent Access**: Templates accessed via Laravel's `resource_path()` helper
- **Version Control**: Templates tracked with Laravel application code
- **Package Integration**: Templates can be published by Laravel packages
- **Asset Management**: Laravel's asset compilation can process templates

## 🔄 Template Processing

Templates are processed by various NetServa services:

1. **ConfigTemplateService** - Handles variable substitution
2. **VhostConfigService** - Generates virtual host configurations
3. **MailConfigService** - Creates mail server configurations
4. **WebConfigService** - Generates web server configurations

## 📋 File Naming Convention

Template files follow NetServa's underscore convention:
- `_etc_service_file.conf` → `/etc/service/file.conf`
- `_srv_domain_file.conf` → `/srv/domain/file.conf`
- `_.well-known_file.php` → `/.well-known/file.php`

## 🔧 Development Notes

When adding new templates:

1. Place in appropriate subdirectory if needed
2. Use consistent variable naming (`{{VARIABLE}}`)
3. Include documentation in this README
4. Add validation in corresponding service classes
5. Include in package publishing if part of a plugin

## 🚨 Security Considerations

- Templates may contain sensitive configuration patterns
- Variable substitution must validate input to prevent injection
- Generated configurations should be validated before deployment
- Access to templates should be restricted in production

## 📈 Future Enhancements

- **Template Validation**: JSON schema validation for template variables
- **Template Testing**: Automated testing of template generation
- **Template Versioning**: Support for multiple template versions
- **Dynamic Templates**: Templates that adapt based on service detection