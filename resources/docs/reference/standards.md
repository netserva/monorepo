# NetServa Official Setup Standards

## "This is the Way" - NetServa Configuration Guidelines

This document establishes the official standards for NetServa Infrastructure Stack (NS) deployment and configuration. These standards ensure consistency, maintainability, and compatibility across all NetServa installations.

---

## Core Principles

### 1. Laravel Compatibility First
All web services must be Laravel-compatible to support the NetServa Central Manager (NS) web interface.

**Web Document Root Standard:**
```
/var/ns/{domain}/var/www/public/  ← REQUIRED (Laravel standard)
```

**NOT:**
```
/var/ns/{domain}/var/www/html/    ← DEPRECATED
```

**Rationale:** Laravel and modern PHP frameworks expect `public/` as the web-accessible directory for security and framework compatibility.

### 2. Directory Structure Standards

#### Top-Level Structure (3-char directories)
```
~/.ns/
├── bin/    # NS executables and tools
├── cfg/    # Configuration templates  
├── doc/    # Documentation and guides
├── etc/    # Infrastructure profiles
├── lib/    # NS libraries and functions
├── log/    # Centralized logging
├── man/    # Manual pages
├── run/    # OpenTofu/Terraform automation
└── var/    # Variable data and domain configs
```

#### Infrastructure Profiles Hierarchy
```
~/.ns/etc/
├── providers/    # Infrastructure providers (BinaryLane, AWS, etc.)
├── hosts/       # Physical servers and VPS hosts  
├── servers/     # Containers, VMs, application servers
└── vhosts/      # Virtual hosts, domains, applications
```

### 3. Environment Variables (5-char NS* naming)
```bash
NSBIN=/home/user/.ns/bin       # Executables
NSCFG=/home/user/.ns/cfg       # Config templates
NSDIR=/home/user/.ns           # Root directory
NSDOC=/home/user/.ns/doc       # Documentation
NSETC=/home/user/.ns/etc       # Infrastructure profiles
NSLIB=/home/user/.ns/lib       # Libraries
NSLOG=/home/user/.ns/log       # Log files
NSMAN=/home/user/.ns/man       # Manual pages
NSRUN=/home/user/.ns/run       # Runtime automation
NSVAR=/home/user/.ns/var       # Variable data
```

---

## Service Configuration Standards

### Web Services (Nginx)

#### Document Root Configuration
**nginx common.conf:**
```nginx
root /var/ns/$host/var/www/public;
index index.html index.php;
```

#### Domain Directory Structure
```
/var/ns/{domain}/
├── var/
│   ├── www/
│   │   ├── public/          ← Web document root (Laravel compatible)
│   │   │   ├── index.html   
│   │   │   ├── index.php    
│   │   │   └── assets/      
│   │   └── storage/         ← Laravel storage directory
│   └── log/                 ← Domain-specific logs
└── mail/                    ← Mail storage (if applicable)
```

### Mail Services Standards

#### Storage Structure
```
/var/mail/{domain}/
└── {username}/
    └── Maildir/
```

#### Configuration Paths
- **Postfix**: `/etc/postfix`
- **Dovecot**: `/etc/dovecot` 
- **DNS**: `/etc/powerdns`
- **SSL**: `/etc/ssl` or `/root/.acme.sh/`

### Database Standards

#### Production Environments
- **MySQL/MariaDB**: Primary choice for production
- **Database per Domain**: `{domain_with_underscores}` naming
- **System Database**: `sysadm` for shared system data

#### Development/Testing
- **SQLite**: Acceptable for lightweight containers
- **Path**: `/var/lib/sqlite/sysadm/`

---

## Operating System Standards

### Supported Vtechs (Priority Order)

1. **Debian Trixie (13)** - Primary production OS
2. **Alpine Linux Edge** - Lightweight containers and development  
3. **CachyOS** - Development workstations
4. **Ubuntu LTS** - Legacy compatibility

### Package Management
- Use distribution package managers (`apt`, `apk`, `pacman`)
- Avoid manual compilation unless absolutely necessary
- Keep package lists minimal for security

### Service Management
- **systemd** (Debian, Ubuntu, CachyOS)
- **OpenRC** (Alpine Linux)
- Use `sc()` function for cross-platform service control

---

## Security Standards

### SSH Configuration
- **Port**: Custom port (not 22) for security
- **Authentication**: Key-based only (disable password auth)
- **Config**: Organized in `~/.ssh/config.d/` directory

### SSL/TLS Standards
- **Certificate Authority**: Let's Encrypt preferred
- **Storage**: `/root/.acme.sh/{domain}_ecc/` or `/etc/ssl/`
- **Renewal**: Automated renewal required
- **Security**: TLS 1.2 minimum, prefer TLS 1.3

### Password Management
- **Generation**: Auto-generated via `/dev/urandom`
- **Length**: Minimum 16 characters
- **Storage**: Environment variables in domain config files
- **Never**: Hardcoded in scripts or version control

---

## Container Standards (LXC/Incus)

### Resource Allocation
- **Production**: 2GB+ RAM, adequate CPU cores
- **Development**: 1GB RAM sufficient for testing
- **Storage**: ZFS with compression and snapshots

### Network Configuration
- **Production**: Public IP addresses
- **Development**: Private network with NAT
- **IPv6**: Enable dual-stack where supported

### Container Naming
- **Format**: Short, memorable names (3-6 characters)
- **Examples**: `mgo`, `motd`, `nsorg`, `mrn`
- **SSH Config**: Match container names for consistency

---

## Profile Documentation Standards

### Infrastructure Profiles Requirements

#### Stack Profiles (`etc/providers/`)
Must document:
- Service offerings and pricing models
- Data center locations and networking
- API access and management interfaces
- Backup and disaster recovery capabilities

#### Host Profiles (`etc/hosts/`)
Must document:
- Hardware specifications and resources
- Operating system and configuration
- Network setup and security
- Cost analysis and capacity planning

#### Server Profiles (`etc/servers/`)
Must document:
- Service stack and configurations
- Resource usage and performance metrics
- Security features and monitoring
- Maintenance recommendations and procedures

#### Vhost Profiles (`etc/vhosts/`)
Must document:
- Domain configuration and services
- Access credentials and interfaces
- File system structure and permissions
- Database and application integration

---

## Development Workflow Standards

### Version Control
- **Primary Repository**: GitHub at `markc/ns`
- **Branching**: Main branch for stable releases
- **Central Authority**: CachyOS workstation for all Git operations
- **Remote Servers**: Use `scp`/`rsync` for updates, NO direct Git

### Documentation Standards
- **Public Documentation**: Sanitized in `doc/` directory
- **Private Documentation**: Real configurations in `doc/private/` (gitignored)
- **Profile Updates**: Update infrastructure profiles after any changes
- **README Files**: Maintain comprehensive project overview

### Testing Standards
- **Container Testing**: Use ZFS snapshots for safe testing
- **Configuration Validation**: Test all nginx/service configs before deployment
- **Backup Verification**: Regular backup restoration testing
- **Security Auditing**: Regular security scans and updates

---

## Compliance Standards

### Data Sovereignty
- **Australian Data**: BinaryLane or Australian providers
- **European Data**: EU-based providers where required
- **Privacy**: GDPR and local privacy law compliance

### Business Requirements
- **SLA**: Production services require uptime guarantees
- **Monitoring**: 24/7 monitoring for production systems
- **Support**: Professional support channels for business-critical services

---

## Migration and Upgrade Standards

### Directory Structure Changes
1. **Plan**: Document changes in this README
2. **Test**: Implement in development environment first
3. **Backup**: Full backup before production changes
4. **Execute**: Systematic rollout across infrastructure
5. **Validate**: Verify all services operational
6. **Document**: Update all affected profiles

### Service Upgrades
- **Staging**: Test in containers first
- **Compatibility**: Ensure Laravel compatibility maintained
- **Rollback Plan**: Always have rollback procedures
- **Monitoring**: Enhanced monitoring during upgrades

---

## Emergency Procedures

### Service Recovery
1. **Assessment**: Identify scope of issue
2. **Containment**: Prevent further damage
3. **Recovery**: Restore from known-good backups
4. **Validation**: Verify all services operational
5. **Documentation**: Update profiles with lessons learned

### Disaster Recovery
- **RTO**: 4-hour recovery time objective
- **RPO**: 24-hour recovery point objective  
- **Backup Verification**: Monthly restoration testing
- **Communication**: Stakeholder notification procedures

---

## Quality Assurance Checklist

### New Domain Setup
- [ ] Laravel-compatible `public/` directory structure
- [ ] SSL certificate configured and auto-renewing
- [ ] DNS records properly configured
- [ ] Mail services tested (if applicable)
- [ ] Monitoring configured
- [ ] Backup procedures verified
- [ ] Infrastructure profiles updated

### Server Deployment
- [ ] OS meets NetServa standards
- [ ] Security hardening applied
- [ ] Service configurations validated
- [ ] Resource monitoring configured
- [ ] Backup systems operational
- [ ] Documentation profiles created
- [ ] Access credentials secured

---

This document serves as the authoritative guide for all NetServa Infrastructure Stack deployments. Adherence to these standards ensures consistency, security, and maintainability across the entire NetServa ecosystem.

**Last Updated:** 2025-08-08  
**Version:** 1.0  
**Maintained By:** NetServa Core Team