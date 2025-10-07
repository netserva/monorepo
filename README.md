# NetServa Platform (NS)

**NS** is a comprehensive, plugin-based Laravel application for managing server infrastructure through a unified web interface and command-line tools. Built on Laravel 12 + Filament 4.0, NS transforms traditional bash-based server management into a modern, testable, and maintainable platform.

## 🎯 Vision

NS provides a unified management platform for:
- **Virtual Machines (VM)** - Complete lifecycle management
- **LXC Containers (CT)** - Lightweight container orchestration  
- **Commercial VPS** - Multi-provider VPS management
- **Infrastructure Services** - DNS, SSL, monitoring, backups, and more

## 🚀 Key Features

### Plugin-Based Architecture
- **Modular Design**: 20+ specialized plugins for different infrastructure components
- **Unified Interface**: Both CLI and web interfaces for every plugin
- **Hot-Pluggable**: Enable/disable plugins without system restart
- **Auto-Discovery**: Plugins automatically register resources and commands

### Modern Technology Stack
- **Laravel 12** - Latest framework with streamlined structure
- **Filament 4.0** - Modern admin interface with real-time updates
- **Pest 4.0** - Comprehensive testing with browser testing support
- **Laravel Prompts** - Beautiful CLI interactions with progress bars
- **phpseclib 3.x** - Secure SSH connections without certificate complexity

### Comprehensive Infrastructure Management
- **SSH Management** - Hosts, keys, connections with automated testing
- **DNS Management** - Multi-provider DNS with PowerDNS integration
- **SSL Management** - Certificate lifecycle with ACME automation
- **Server Setup** - Automated deployment templates and workflows
- **Migration Tools** - Server migration with assessment and validation
- **Backup Management** - Automated backups with retention policies
- **Monitoring** - Real-time health checks and alerting

## 🎉 Pure Laravel Architecture Achievement

**NetServa 3.0** represents a complete architectural transformation:

### ✅ **100% Laravel Operation**
- **Pure PHP**: All infrastructure operations now run through Laravel services
- **No Bash Dependencies**: Eliminated 63 bash scripts and 88 library files
- **Service-Based Architecture**: 74 Laravel services handle all functionality
- **Backward Compatibility**: 67 bash function wrappers maintain familiar command interface

### 🔄 **Seamless Migration**
```bash
# User runs familiar commands
addvhost example.com --shost=server1
chperms example.com
migrate server assess

# Functions automatically call Laravel
cd "$NSDIR" && php artisan addvhost "$@"
cd "$NSDIR" && php artisan chperms "$@"
cd "$NSDIR" && php artisan platform:migrate "$@"
```

### 🏗️ **Modern Architecture Flow**
```
User Command → Function Wrapper → Laravel Command → Laravel Service → Result
     ↓              ↓                ↓               ↓            ↓
addvhost      →  addvhost()  →  php artisan  →  VhostManagement  →  ✅
example.com      function        addvhost         Service
```

## 📦 Plugin Architecture

**11 NetServa Plugins** providing comprehensive infrastructure management:

- **netserva-core** - Foundation models, services, and database migrations
- **netserva-cli** - Command-line tools and bash function wrappers
- **netserva-config** - Configuration management and templates
- **netserva-cron** - Scheduled task management and automation
- **netserva-dns** - DNS zones, records, and PowerDNS integration
- **netserva-fleet** - Multi-server infrastructure management
- **netserva-ipam** - IP address management and network allocation
- **netserva-mail** - Email server configuration (Postfix/Dovecot/Rspamd)
- **netserva-ops** - Operational tools and server administration
- **netserva-web** - Web server and virtual host management (Nginx/PHP-FPM)
- **netserva-wg** - WireGuard VPN management

## 🆕 Status (2025-10-04)

### ✅ Core Architecture Complete
- **Laravel 12 + Filament 4.0** - Modern PHP framework with admin interface
- **Plugin System** - 11 NetServa plugins with Pest 4.0 test coverage
- **Pure Laravel** - Eliminated bash dependencies, 100% PHP operation
- **Database Schema** - SQLite for workstation, MySQL/MariaDB for production
- **CLI + Web** - Dual interface for all infrastructure operations

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
# SSH Management
php artisan ssh:host list          # List all SSH hosts
php artisan ssh:host create        # Create new SSH host
php artisan ssh:key generate       # Generate SSH key pairs
php artisan ssh:connection test    # Test SSH connections

# DNS Management  
php artisan dns:zone list          # List DNS zones
php artisan dns:record sync        # Sync DNS records

# Server Setup
php artisan setup:server           # Interactive server setup
php artisan migrate:assess         # Assess server for migration

# Plugin Management
php artisan plugin:list            # List installed plugins
php artisan plugin:sync            # Sync plugin registry
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

## 🗄️ Database Support

- **SQLite** - Development and single-server deployments
- **MySQL/MariaDB** - Production multi-server setups
- **Migrations** - Plugin-specific database migrations
- **Factories** - Test data generation for all models

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

## 🔄 Migration from Bash Scripts

NS migrates functionality from traditional bash scripts while preserving all capabilities:

### Legacy → Modern
- `bin/migrate` → `php artisan migrate:assess`
- `bin/setup-*` → `php artisan setup:server`
- `bin/sshm` → `php artisan ssh:host|key|connection`
- Manual configs → Database-driven with web interface

### Benefits
- **Testability** - Comprehensive test coverage with Pest
- **Reliability** - Database transactions and error handling
- **Usability** - Beautiful web interface and CLI prompts
- **Maintainability** - Modern PHP patterns and documentation

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

- **PHP 8.2+** - Modern PHP with latest features
- **Composer 2.x** - Dependency management
- **Node.js 18+** - Frontend asset compilation
- **SQLite/MySQL** - Database backend
- **SSH Access** - For remote server management

## 🔗 Related Projects

- **NetServa Shell Enhancement (`~/.sh/`)** - Foundational shell utilities
- **NetServa Platform (`~/.ns/`)** - Complete infrastructure management

---

**NetServa Platform (NS)** - Modern infrastructure management for the cloud-native era.

*Built with ❤️ using Laravel, Filament, and Pest*
