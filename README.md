# NetServa 3.0 Platform

**Modern infrastructure management built on Laravel 12 + Filament 4.0 + Pest 4.0**

NetServa 3.0 Platform (NS) is a comprehensive, plugin-based Laravel application for managing multi-server infrastructure through a unified web interface and command-line tools. Built with database-first architecture and real-time monitoring.

## 🎯 Platform Hierarchy

**6-Layer Infrastructure Model:**

```
venue → vsite → vnode → vhost + vconf → vserv
```

1. **venue** - Physical location/datacenter (e.g., `home-lab`, `sydney-dc`)
2. **vsite** - Logical grouping (e.g., `production`, `staging`)
3. **vnode** - Server/VM/container (e.g., `markc` at 192.168.1.227)
4. **vhost** - Virtual hosting domain (e.g., `markc.goldcoast.org`)
5. **vconf** - Configuration variables (54+ vars in `vconfs` table)
6. **vserv** - Services (nginx, php-fpm, postfix, dovecot)

## 🌐 Infrastructure Support

- **Proxmox VE** - Virtual machine management
- **Incus (LXC)** - Container orchestration
- **Commercial VPS** - Multi-provider support
- **Physical Servers** - Bare metal deployment

## 🚀 Key Features

### Plugin-Based Architecture
- **Modular Design**: 20+ specialized plugins for different infrastructure components
- **Unified Interface**: Both CLI and web interfaces for every plugin
- **Hot-Pluggable**: Enable/disable plugins without system restart
- **Auto-Discovery**: Plugins automatically register resources and commands

### Technology Stack
- **Laravel 12** - Modern framework with streamlined structure
- **Filament 4.0** - Admin interface (STABLE) with real-time updates
- **Pest 4.0** - Comprehensive testing with browser support
- **Laravel Prompts** - Beautiful CLI interactions
- **phpseclib 3.x** - SSH without certificate complexity
- **vconfs Table** - Database-first configuration (54+ vars per vhost)

### Infrastructure Management
- **SSH** - Hosts, keys, connections with automated testing
- **DNS** - Multi-provider with PowerDNS integration
- **SSL** - Certificate lifecycle with ACME automation
- **Deployment** - Automated server setup workflows
- **Migration** - Server migration with assessment tools
- **Backup** - Automated with retention policies
- **Monitoring** - Real-time health checks and alerting

## 📦 Plugin Architecture

**11 NetServa Plugins** providing comprehensive infrastructure management:

- **netserva-core** - Foundation models, services, database migrations
- **netserva-cli** - Command-line tools, VHost/VConf management
- **netserva-config** - Configuration templates and management
- **netserva-cron** - Scheduled tasks and automation
- **netserva-dns** - DNS zones, records, PowerDNS integration
- **netserva-fleet** - Multi-server infrastructure (VNode/VHost/VConf)
- **netserva-ipam** - IP address management, network allocation
- **netserva-mail** - Email servers (Postfix/Dovecot/Rspamd)
- **netserva-ops** - Operational tools, server administration
- **netserva-web** - Web servers, virtual hosts (Nginx/PHP-FPM)
- **netserva-wg** - WireGuard VPN management

## 🗄️ Database-First Architecture

**ALL configuration stored in Laravel database - NO flat files.**

### vconfs Table Structure
```sql
CREATE TABLE vconfs (
    id BIGINT,
    fleet_vhost_id BIGINT,           -- Links to fleet_vhosts
    name VARCHAR(5),                  -- 5-char variable (WPATH, DPASS)
    value TEXT,                       -- Variable value
    category VARCHAR(20),             -- paths, credentials, settings
    is_sensitive BOOLEAN,             -- Password masking
    UNIQUE(fleet_vhost_id, name)
);
```

**54+ environment variables per vhost** - each as a separate database row.

## 🆕 Status (2025-10-08)

### ✅ Architecture Complete
- **Laravel 12 + Filament 4.0** - Modern PHP stack with admin interface
- **11 Plugins** - Comprehensive coverage with Pest 4.0 tests
- **vconfs Table** - Database-first configuration (54+ vars per vhost)
- **Platform Hierarchy** - 6-layer model (venue → vsite → vnode → vhost + vconf → vserv)
- **Dual Interface** - CLI + Web for all operations

## 🖥️ Quick Start

### Installation

1. **Clone and Setup**
   ```bash
   git clone <repository-url>
   cd ns
   composer install
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   touch database/database.sqlite
   php artisan migrate
   ```

3. **Start Development Server**
   ```bash
   composer run dev
   # Runs: serve + queue + logs + vite in parallel
   ```

4. **Access Web Interface**
   - Navigate to `http://localhost:8000`
   - Redirects to `/admin` (Filament dashboard)
   - Guest mode enabled for development

### Command Line Usage

```bash
# VHost Management (positional: vnode vhost)
php artisan addvhost markc example.com
php artisan chvhost markc example.com --php-version=8.4
php artisan shvhost markc example.com
php artisan chperms markc example.com

# VConf Management (vconfs table)
php artisan shvconf markc example.com              # Show all variables
php artisan shvconf markc example.com WPATH        # Show specific variable
php artisan chvconf markc example.com WPATH /srv/example.com/web
php artisan addvconf markc example.com             # Initialize with defaults

# Fleet Discovery
php artisan fleet:discover --vnode=markc           # Import infrastructure

# SSH/DNS/SSL Management
php artisan ssh:host list                          # List SSH hosts
php artisan dns:zone list                          # List DNS zones
php artisan ssl:cert list                          # List SSL certificates
```

## 🧪 Testing

NS uses **Pest 4.0** for comprehensive testing:

```bash
# Run all tests
php artisan test

# Test individual plugins
php artisan test packages/netserva-cli/tests
php artisan test packages/netserva-config/tests
php artisan test packages/netserva-core/tests
php artisan test packages/netserva-cron/tests
php artisan test packages/netserva-dns/tests
php artisan test packages/netserva-fleet/tests
php artisan test packages/netserva-ipam/tests
php artisan test packages/netserva-mail/tests
php artisan test packages/netserva-ops/tests
php artisan test packages/netserva-web/tests
php artisan test packages/netserva-wg/tests

# Run with filters
php artisan test --filter=SshHostTest
php artisan test tests/Feature/
```

## 🔧 Development

### Creating New Plugins

```bash
# Generate plugin scaffolding
php artisan make:plugin ExampleManager

# Generated structure:
packages/ns-example/
├── src/
│   ├── ExampleServiceProvider.php
│   ├── Console/Commands/
│   ├── Filament/Resources/
│   ├── Models/
│   └── Services/
├── database/migrations/
├── tests/
└── composer.json
```

### Plugin Development Guidelines

1. **Dual Interface**: Every plugin provides both CLI and web interfaces
2. **Service Layer**: Business logic in services, reused by CLI and web
3. **Laravel Patterns**: Follow Laravel conventions for consistency
4. **Testing**: Comprehensive Pest tests for all functionality
5. **Documentation**: Clear README with usage examples

### Code Style & Quality

```bash
# Format code
vendor/bin/pint

# Run tests before commits
php artisan test

# Static analysis
vendor/bin/phpstan analyse
```

## 🗄️ Database

- **SQLite** - Development, single-server
- **MySQL/MariaDB** - Production, multi-server
- **vconfs Table** - 54+ configuration variables per vhost
- **Migrations** - Plugin-specific schemas
- **Factories** - Test data generation

## 🔐 Security

### Features
- **SSH Key Management** - Ed25519 by default, secure file permissions
- **Certificate-Free SSH** - Uses phpseclib without certificate complexity  
- **Secrets Management** - Encrypted credential storage with access logging
- **Audit Logging** - Comprehensive activity tracking
- **Role-Based Access** - Filament authentication and authorization

### Best Practices
- All passwords auto-generated via `/dev/urandom`
- No hardcoded credentials in codebase
- File permissions automatically secured (600/644)
- Environment-based configuration separation

## 📊 Monitoring & Analytics

### Real-Time Monitoring
- **System Health** - Service status and performance metrics
- **SSH Connectivity** - Connection testing and monitoring
- **SSL Certificates** - Expiration tracking and renewal automation
- **Backup Status** - Job completion and retention monitoring

### Analytics & Reporting
- **Infrastructure Overview** - Dashboards with key metrics
- **Audit Reports** - Compliance and activity summaries  
- **Performance Analytics** - Resource utilization trends
- **Cost Tracking** - Resource usage and optimization insights

## 🔄 NetServa 3.0 Evolution

**From Bash to Laravel** - Complete architectural transformation:

### Modern Architecture
- **Database-First** - All config in `vconfs` table (54+ vars per vhost)
- **Platform Hierarchy** - 6-layer model (venue → vsite → vnode → vhost + vconf → vserv)
- **Positional Commands** - `<command> <vnode> <vhost>` convention
- **Dual Interface** - Laravel CLI + Filament 4.0 web interface

### Benefits
- **Testability** - Pest 4.0 comprehensive coverage
- **Reliability** - Database transactions, error handling
- **Usability** - Laravel Prompts + Filament interface
- **Maintainability** - Modern PHP patterns, documentation

## 📚 Documentation

- **Plugin READMEs** - Each plugin includes comprehensive documentation
- **API Documentation** - Generated from code annotations
- **Command Help** - `php artisan help <command>` for detailed usage
- **Web Interface** - Contextual help and tooltips throughout

## 🤝 Contributing

1. **Fork & Clone** - Standard GitHub workflow
2. **Plugin Development** - Use `make:plugin` for new functionality
3. **Testing** - Ensure all tests pass with comprehensive coverage
4. **Documentation** - Update relevant README files
5. **Pull Request** - Clear description of changes and testing

## 📄 License

MIT License - See LICENSE file for full details.

## 🛠️ System Requirements

- **PHP 8.4+** - Modern PHP with latest features
- **Composer 2.x** - Dependency management
- **Node.js 18+** - Frontend asset compilation
- **SQLite/MySQL** - Database (SQLite dev, MySQL/MariaDB production)
- **SSH Access** - Remote server management (phpseclib 3.x)

## 🔗 Related Projects

- **NetServa Shell Enhancement (`~/.rc/`)** - Foundational shell utilities
- **NetServa 3.0 Platform (`~/.ns/`)** - Complete infrastructure management

---

**NetServa 3.0 Platform** - Modern infrastructure management with database-first architecture.

*Built with Laravel 12 + Filament 4.0 + Pest 4.0*
