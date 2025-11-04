# NetServa Platform

Complete infrastructure management platform for Laravel.

## Installation

```bash
composer require netserva/platform:^0.0.1
```

## Packages Included

This meta-package installs all NetServa packages for comprehensive infrastructure management:

### Core Infrastructure
- **netserva/core** - Foundation package with core models, services, and database architecture
- **netserva/cli** - Unified command-line interfaces with Laravel Prompts
- **netserva/fleet** - VNode/VHost infrastructure tracking and orchestration

### Network Services
- **netserva/dns** - DNS & Domain management with PowerDNS integration
- **netserva/ipam** - IP Address Management for network planning and allocation
- **netserva/wg** - WireGuard VPN management and peer orchestration

### Server Services
- **netserva/web** - Web server orchestration (Nginx/Apache) with SSL certificate management
- **netserva/mail** - Complete email infrastructure (Postfix/Dovecot) with mailbox administration
- **netserva/config** - Configuration & secrets management for all infrastructure components

### Operations
- **netserva/ops** - Monitoring, backup management, analytics, and observability platform
- **netserva/cron** - Task automation, scheduling, and workflow orchestration
- **netserva/cms** - Professional CMS with Filament 4 admin panel

## À La Carte Installation

Install individual packages as needed:

```bash
# DNS management only
composer require netserva/dns:^0.0.1

# Email infrastructure only
composer require netserva/mail:^0.0.1

# Web services + DNS (web requires dns)
composer require netserva/web:^0.0.1

# VPN with IP management (wg requires ipam)
composer require netserva/wg:^0.0.1
```

## Requirements

- PHP ^8.2 or higher
- Laravel ^12.0
- Filament ^4.0

## Alpha Notice

⚠️ All packages are currently v0.0.x (alpha stage). APIs may change between releases.

## Documentation

- [Installation Guide](https://github.com/netserva/monorepo/blob/main/docs/INSTALLATION.md)
- [Publishing Guide](https://github.com/netserva/monorepo/blob/main/docs/PUBLISHING.md) (for maintainers)
- [Full Documentation](https://netserva.org/docs)

## Development

Development happens in the monorepo: https://github.com/netserva/monorepo

## License

MIT License. See [LICENSE](https://github.com/netserva/monorepo/blob/main/LICENSE) for details.

## Support

- **Issues:** https://github.com/netserva/monorepo/issues
- **Source:** https://github.com/netserva/monorepo
- **Website:** https://netserva.org
