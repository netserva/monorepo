# Cloud-Init Integration for NetServa 3.0

**Last Updated:** 2025-10-17
**Status:** Production Ready

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [What is Cloud-Init?](#what-is-cloud-init)
3. [NetServa FQDN Strategy](#netserva-fqdn-strategy)
4. [Cloud-Init Configuration](#cloud-init-configuration)
5. [Incus Integration](#incus-integration)
6. [Alpine Linux Specifics](#alpine-linux-specifics)
7. [Example Configurations](#example-configurations)
8. [Troubleshooting](#troubleshooting)

---

## Overview

NetServa 3.0 uses a **FCrDNS-first, /etc/hosts fallback** strategy for vnode hostname resolution:

```
┌─────────────────────────────────────────────────────────────┐
│ NetServa FQDN Resolution Strategy                           │
├─────────────────────────────────────────────────────────────┤
│ 1. ✅ FCrDNS Validation (A + PTR records) - PREFERRED       │
│    └─> email_capable: true                                  │
│                                                              │
│ 2. ⚠️  /etc/hosts Fallback (no DNS records)                 │
│    └─> email_capable: false                                 │
│                                                              │
│ 3. ❌ Configuration Failed                                  │
│    └─> Manual intervention required                         │
└─────────────────────────────────────────────────────────────┘
```

### Key Decisions

| Component | Decision | Rationale |
|-----------|----------|-----------|
| **DNS Requirement** | Optional (was mandatory) | Enables offline/dev environments |
| **/etc/hosts Fallback** | Automatic | Universal across all Linux distros |
| **email_capable Flag** | FCrDNS-dependent | Accurate mail server capability tracking |
| **Cloud-Init** | Optional enhancement | Streamlines Incus/LXD container setup |

---

## What is Cloud-Init?

**Cloud-init** is the industry-standard multi-distribution method for cross-platform cloud instance initialization.

### Core Features

- **Runs once on first boot** (before networking/SSH)
- **Multi-distro support** (Alpine, Debian, Ubuntu, RHEL, etc.)
- **YAML configuration** (`#cloud-config` format)
- **Idempotent** (safe to re-run during development)

### NetServa Use Cases

| Use Case | Benefit |
|----------|---------|
| **Hostname/FQDN Setup** | Automatic /etc/hosts configuration |
| **User Creation** | Provision `sysadm` user with SSH keys |
| **Package Installation** | Install essential tools (curl, vim, htop) |
| **Network Configuration** | Static IP for production servers |
| **Service Hardening** | Disable password auth, configure SSH |

---

## NetServa FQDN Strategy

### The `/proc/sys/kernel/domainname` Myth

❌ **WRONG:** `/proc/sys/kernel/domainname` is for **NIS/YP domains** (1990s legacy)
✅ **CORRECT:** Use `/etc/hosts` for FQDN resolution

```bash
# This does NOTHING for DNS or hostname -f:
echo "netserva.com" > /proc/sys/kernel/domainname

# This WORKS:
echo "127.0.1.1  vnode1.netserva.com vnode1" >> /etc/hosts
hostname vnode1
hostname -f  # → vnode1.netserva.com ✅
```

### FQDN Resolution Order

1. **FCrDNS (Forward-Confirmed Reverse DNS)**
   ```bash
   # A record
   dig vnode1.netserva.com → 192.168.1.100

   # PTR record
   dig -x 192.168.1.100 → vnode1.netserva.com

   # ✅ FCrDNS valid: A + PTR match
   ```

2. **/etc/hosts Fallback**
   ```bash
   # /etc/hosts
   127.0.1.1  vnode1.netserva.com vnode1

   # Works offline, no DNS required
   hostname -f → vnode1.netserva.com
   ```

3. **DNS-Only (A record)**
   ```bash
   # A record exists, but no PTR
   # ⚠️ Works for most purposes, but NOT email_capable
   ```

---

## Cloud-Init Configuration

### Basic FQDN Configuration

```yaml
#cloud-config
hostname: vnode1
fqdn: vnode1.netserva.com
prefer_fqdn_over_hostname: true
manage_etc_hosts: true  # ← KEY: Auto-configures /etc/hosts
```

**Generated `/etc/hosts`:**
```
127.0.0.1  localhost
127.0.1.1  vnode1.netserva.com vnode1

::1  localhost ip6-localhost ip6-loopback
```

### NetServa Production Template

```yaml
#cloud-config
# NetServa 3.0 VNode Initialization

# Hostname Configuration
hostname: vnode1
fqdn: vnode1.netserva.com
prefer_fqdn_over_hostname: true
manage_etc_hosts: true

# System Configuration
timezone: UTC
locale: en_US.UTF-8

# Package Management
package_update: true
package_upgrade: false  # Control upgrades explicitly
packages:
  - curl
  - wget
  - vim
  - htop
  - net-tools
  - bind-tools  # dig (Alpine)

# User Configuration
users:
  - name: sysadm
    groups: wheel  # sudo equivalent on Alpine
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    lock_passwd: false
    ssh_authorized_keys:
      - ssh-ed25519 AAAA...key...here user@workstation

# SSH Hardening
ssh_pwauth: false
disable_root: false

# Custom Files
write_files:
  - path: /etc/motd
    content: |
      NetServa 3.0 VNode: vnode1.netserva.com
      Managed by NetServa CLI
    permissions: '0644'

# Post-Install Commands
runcmd:
  - echo "NetServa vnode initialized" >> /var/log/cloud-init.log
```

---

## Incus Integration

### Prerequisites

```bash
# List available cloud images
incus image list images: alpine/3.20 --format table

# Ensure you select the 'cloud' variant (NOT default!)
# ✅ images:alpine/3.20/cloud
# ❌ images:alpine/3.20 (no cloud-init support)
```

### Method 1: Inline Configuration

```bash
incus init images:alpine/3.20/cloud vnode1 \
  -c cloud-init.user-data="#cloud-config
hostname: vnode1
fqdn: vnode1.netserva.com
manage_etc_hosts: true"

incus start vnode1
```

### Method 2: File-Based Configuration (Recommended)

```bash
# Create cloud-init config
cat > /tmp/vnode1-cloud-init.yaml << 'EOF'
#cloud-config
hostname: vnode1
fqdn: vnode1.netserva.com
manage_etc_hosts: true

users:
  - name: sysadm
    groups: wheel
    sudo: ALL=(ALL) NOPASSWD:ALL
    ssh_authorized_keys:
      - ssh-ed25519 AAAA...
EOF

# Initialize container
incus init images:alpine/3.20/cloud vnode1 \
  -c cloud-init.user-data="$(cat /tmp/vnode1-cloud-init.yaml)"

incus start vnode1
```

### Method 3: Profile-Based (Best for Multiple Vnodes)

```bash
# Create NetServa profile
incus profile create netserva-vnode

# Configure profile
cat > /tmp/netserva-profile.yaml << 'EOF'
config:
  cloud-init.vendor-data: |
    #cloud-config
    package_update: true
    packages:
      - curl
      - vim
      - htop
    users:
      - name: sysadm
        groups: wheel
        sudo: ALL=(ALL) NOPASSWD:ALL
EOF

cat /tmp/netserva-profile.yaml | incus profile edit netserva-vnode

# Launch vnodes with profile
incus launch images:alpine/3.20/cloud vnode1 \
  -p default -p netserva-vnode \
  -c cloud-init.user-data="#cloud-config
hostname: vnode1
fqdn: vnode1.netserva.com
manage_etc_hosts: true"
```

### Verification

```bash
# Check cloud-init status
incus exec vnode1 -- cloud-init status

# View cloud-init logs
incus exec vnode1 -- cat /var/log/cloud-init.log
incus exec vnode1 -- cat /var/log/cloud-init-output.log

# Verify hostname
incus exec vnode1 -- hostname -f
# → vnode1.netserva.com ✅
```

---

## Alpine Linux Specifics

### Package Differences

| Feature | Alpine | Debian/Ubuntu |
|---------|--------|---------------|
| **Package Manager** | `apk` | `apt` |
| **Service Manager** | OpenRC | systemd |
| **Sudo Group** | `wheel` | `sudo` |
| **DNS Tools** | `bind-tools` | `dnsutils` |

### Alpine Cloud-Init Example

```yaml
#cloud-config
# Alpine-specific configuration

hostname: vnode1
fqdn: vnode1.netserva.com
manage_etc_hosts: true

packages:
  - sudo        # Not installed by default!
  - bash        # Alpine uses ash by default
  - shadow      # For useradd/usermod
  - bind-tools  # dig command

users:
  - name: sysadm
    groups: wheel  # ← Alpine's sudo group
    sudo: ALL=(ALL) NOPASSWD:ALL

runcmd:
  - rc-update add sshd default  # ← OpenRC, not systemd
  - rc-service sshd start
```

### Alpine Service Management

```bash
# Enable service at boot
rc-update add sshd default

# Start service
rc-service sshd start

# Check status
rc-status
```

---

## Example Configurations

### Development Vnode (No DNS)

```yaml
#cloud-config
hostname: dev1
fqdn: dev1.netserva.local  # .local for dev
manage_etc_hosts: true     # Critical for offline work

# Minimal packages
packages:
  - vim
  - curl
```

### Production Mail Server (FCrDNS Required)

```yaml
#cloud-config
hostname: mail1
fqdn: mail1.netserva.com
manage_etc_hosts: true

# NOTE: Still configure /etc/hosts, but FCrDNS validation
# happens in NetServa discovery service

packages:
  - postfix
  - dovecot
  - bind-tools

# Ensure hostname is set before mail server installation
runcmd:
  - hostname -f || echo "FQDN not configured!"
```

### Static IP Configuration

```yaml
#cloud-config
hostname: vnode1
fqdn: vnode1.netserva.com
manage_etc_hosts: true

# Network configuration
network:
  version: 2
  ethernets:
    eth0:
      addresses:
        - 192.168.1.100/24
      gateway4: 192.168.1.1
      nameservers:
        addresses:
          - 192.168.1.1
          - 1.1.1.1
```

---

## Troubleshooting

### Cloud-Init Not Running

```bash
# Check cloud-init status
incus exec vnode1 -- cloud-init status --wait

# View detailed logs
incus exec vnode1 -- cat /var/log/cloud-init.log
incus exec vnode1 -- cloud-init analyze show

# Force re-run (for testing)
incus exec vnode1 -- cloud-init clean --logs
incus restart vnode1
```

### hostname -f Fails

```bash
# Check /etc/hosts
incus exec vnode1 -- cat /etc/hosts

# Should contain:
# 127.0.1.1  vnode1.netserva.com vnode1

# Check /etc/hostname
incus exec vnode1 -- cat /etc/hostname
# → vnode1

# Manually fix
incus exec vnode1 -- sh -c 'echo "127.0.1.1  vnode1.netserva.com vnode1" >> /etc/hosts'
incus exec vnode1 -- hostname vnode1
```

### FCrDNS Validation Fails

```bash
# NetServa will automatically fall back to /etc/hosts
# Check vnode status
php artisan shvnode vnode1

# Output shows:
# ⚠️  FCrDNS: Not available (using /etc/hosts fallback)
# ❌ Email Capable: No (DNS records required)

# To fix: Add DNS records manually
dig vnode1.netserva.com      # Should return A record
dig -x 192.168.1.100          # Should return PTR record

# Re-run discovery
php artisan fleet:discover --vnode=vnode1
```

### Wrong Image Type (No Cloud-Init)

```bash
# ❌ Wrong
incus launch images:alpine/3.20 vnode1

# ✅ Correct
incus launch images:alpine/3.20/cloud vnode1

# Verify image has cloud-init
incus image info images:alpine/3.20/cloud | grep cloud
```

---

## NetServa CLI Integration

### Current Behavior

```bash
# Add vnode with discovery
php artisan addvnode mysite vnode1 myhost --discover

# Output:
# ✅ Checking FCrDNS for existing FQDN
#    └─> FCrDNS validation PASSED
#    └─> email_capable: true
#
# OR:
#
# ⚠️  FCrDNS not available, configuring /etc/hosts fallback
#    └─> hostname -f: vnode1.netserva.local
#    └─> email_capable: false
```

### Manual Hostname Validation

```php
// In Tinker or custom command
$vnode = FleetVNode::where('name', 'vnode1')->first();
$validation = $vnode->validateHostnameResolution();

// Returns:
// [
//   'success' => true,
//   'fqdn' => 'vnode1.netserva.com',
//   'error' => null,
// ]
```

---

## Best Practices

### ✅ DO

- Use `manage_etc_hosts: true` in cloud-init configs
- Select **cloud** variant images for Incus (`images:alpine/3.20/cloud`)
- Configure cloud-init **before** `incus start`
- Use profiles for consistent vnode templates
- Verify hostname with `hostname -f` after initialization

### ❌ DON'T

- Don't rely on `/proc/sys/kernel/domainname` for FQDN
- Don't skip `/etc/hosts` configuration
- Don't assume FCrDNS is always available
- Don't use default images (non-cloud variants)
- Don't modify cloud-init configs after first boot

---

## References

- [Cloud-Init Official Docs](https://cloudinit.readthedocs.io/)
- [Incus Cloud-Init Guide](https://linuxcontainers.org/incus/docs/main/cloud-init/)
- [Alpine Cloud-Init Support](https://wiki.alpinelinux.org/wiki/Cloud-init)
- NetServa: `packages/netserva-fleet/src/Services/FleetDiscoveryService.php:505`
- NetServa: `packages/netserva-fleet/src/Models/FleetVNode.php:375`

---

**Last Updated:** 2025-10-17
**Maintained By:** NetServa Development Team
