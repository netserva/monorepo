# NetServa Fleet Management Plugin

A comprehensive infrastructure management plugin for NetServa that tracks and manages your VSite→VNode→VHost hierarchy across mixed infrastructure environments.

## Overview

NetServa Fleet provides unified management for:
- **VSites** - Hosting providers/locations (local Incus, BinaryLane VPS, customer hardware)
- **VNodes** - Physical/virtual servers
- **VHosts** - VM/CT instances (complete virtualized environments, not just web vhosts)

## Key Features

### 🏗️ Three-Tier Infrastructure Hierarchy
- **VSite** (hosting provider/location) → **VNode** (server) → **VHost** (VM/CT instance)
- Supports mixed infrastructure: local Incus, local Proxmox, BinaryLane VPS, customer hardware
- Role-based node classification: compute, network, storage, mixed

### 🔍 SSH-Based Discovery
- Automatic system information discovery via SSH
- Graceful error handling for unreachable nodes
- Role-specific discovery commands for different node types
- Scheduled scanning with configurable frequency

### 📊 Comprehensive Management Interface
- Full Filament 4.0 admin interface for fleet management
- Real-time discovery status and system information
- Bulk operations for discovery and management
- Integration with existing NetServa SSH host management

### 📁 NetServa Integration
- Import existing infrastructure from `var/` directory structure
- Sync with NetServa 54-variable environment files
- Support for `/srv/` filesystem layout standard
- Integration with existing SSH host configurations

## Installation

1. Add to your Laravel application's composer.json:
```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/netserva-fleet"
    }
]
```

2. Install the package:
```bash
composer require netserva/netserva-fleet
```

3. Run migrations:
```bash
php artisan migrate
```

4. Publish configuration (optional):
```bash
php artisan vendor:publish --tag=fleet-config
```

## Usage

### Import Existing Infrastructure

Import from your existing `var/` directory structure:

```bash
# Dry run to see what would be imported
php artisan fleet:import --dry-run

# Import all infrastructure
php artisan fleet:import

# Import specific vnode only
php artisan fleet:import --vnode=mgo

# Force overwrite existing data
php artisan fleet:import --force
```

### Discovery Management

Discover system information via SSH:

```bash
# Discover all nodes that need scanning
php artisan fleet:discover

# Discover specific vnode
php artisan fleet:discover --vnode=mgo

# Force discovery even if not scheduled
php artisan fleet:discover --force

# Test SSH connections only
php artisan fleet:discover --test-ssh
```

### Web Interface

Access the fleet management interface at `/admin/fleet-vsites` in your Filament admin panel.

## Configuration

### VSite Directory Mappings

Configure how `var/` directory names map to VSites in `config/fleet.php`:

```php
'vsites' => [
    'directory_mappings' => [
        'mgo' => ['provider' => 'local', 'technology' => 'incus', 'location' => 'workstation'],
        'nsorg' => ['provider' => 'binarylane', 'technology' => 'vps', 'location' => 'sydney'],
    ],
],
```

### Discovery Commands

Customize discovery commands for different node roles:

```php
'discovery' => [
    'discovery_commands' => [
        'compute' => [
            'hostname -f',
            'uname -a',
            'cat /proc/cpuinfo | grep "processor" | wc -l',
            // ... more commands
        ],
        'network' => [
            'hostname',
            'uptime',
            'ip addr show',
        ],
    ],
],
```

## Architecture

### Database Schema

#### fleet_vsites (Hosting Providers)
- Tracks hosting providers and technologies
- Unique constraint on provider+technology+location
- Capabilities and API endpoint configuration

#### fleet_vnodes (Servers)
- Physical/virtual servers with SSH access
- Role-based classification and discovery
- System information and discovery status
- Links to SSH host configurations

#### fleet_vhosts (VM/CT Instances)
- Complete virtualized environments
- NetServa environment variable integration
- Instance type and resource tracking
- File system integration with var/ directory

### Discovery Architecture

- **Role-based discovery**: Different commands for compute/network/storage nodes
- **Graceful failure handling**: Continue discovery even if some commands fail
- **SSH-only approach**: All discovery via SSH with proper error handling
- **Scheduled scanning**: Configurable frequency with automatic retry for failures

### Error Handling

- SSH connection failures are logged but don't stop discovery
- Command failures are tracked per-node for debugging
- Partial discovery results are saved (get what we can)
- Failed nodes are scheduled for retry at shorter intervals

## Testing

Run the test suite:

```bash
# Run all fleet tests
php artisan test packages/netserva-fleet/tests

# Run specific test groups
php artisan test --group=fleet
php artisan test --group=discovery
php artisan test --group=import
```

## NetServa Compliance

This plugin follows NetServa standards:

- ✅ Uses NetServa 5-character environment variables (`$NSVAR`, `$NSSSH`, etc.)
- ✅ Supports 54-variable NetServa environment files
- ✅ Compatible with `/srv/` filesystem layout
- ✅ Integrates with existing SSH host management
- ✅ Follows MIT license with consistent headers
- ✅ Uses Pest 4.0 for testing
- ✅ Filament 4.0 admin interface

## License

MIT License - see LICENSE file for details.

## Support

For issues and feature requests, please use the main NetServa repository issue tracker.