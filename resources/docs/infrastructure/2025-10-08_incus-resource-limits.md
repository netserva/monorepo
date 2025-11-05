# Incus Container Resource Limits

This guide explains how to set and manage resource limits (CPU, memory, storage) for Incus LXC containers and VMs.

## Overview

Resource limits help prevent containers from consuming excessive system resources and ensure fair resource allocation across multiple containers. Incus supports limits for:
- **Memory** - RAM allocation limits
- **CPU** - Number of CPUs or CPU time allocation
- **Storage** - Disk space limits
- **Network** - Bandwidth limits (not covered here)

## Checking Current Resource Usage

Use the `incus-stats` command to view current resource usage and limits:

```bash
incus-stats
```

Output example:
```
NAME            TYPE     MEM LIMIT    MEM USED     STOR LIMIT   STOR USED    # CPUs    
--------------- -------- ------------ ------------ ------------ ------------ ----------
haproxy         CT       256MiB       34MiB        unlimited    68.06MiB     18 (no limit)
mgo             CT       4GiB         831MiB       200GiB       106.90GiB    18 (no limit)
```

## Memory Limits

### Setting Memory Limits

```bash
# Set memory limit
incus config set <container> limits.memory <size>

# Examples
incus config set haproxy limits.memory 512MiB    # 512 megabytes
incus config set mgo limits.memory 4GiB          # 4 gigabytes
incus config set motd limits.memory 2GB           # 2 gigabytes
```

### Memory Size Units
- `B` - bytes
- `KiB`, `KB` - kilobytes
- `MiB`, `MB` - megabytes  
- `GiB`, `GB` - gigabytes
- `TiB`, `TB` - terabytes

### Remove Memory Limit

```bash
incus config unset <container> limits.memory
```

## CPU Limits

### Setting CPU Count Limits

```bash
# Limit to specific number of CPUs
incus config set <container> limits.cpu <number>

# Examples
incus config set haproxy limits.cpu 2      # Max 2 CPUs
incus config set mgo limits.cpu 4          # Max 4 CPUs
```

### CPU Pinning (Advanced)

```bash
# Pin to specific CPU cores
incus config set <container> limits.cpu 0-3    # Use CPUs 0,1,2,3
incus config set <container> limits.cpu 0,2    # Use CPUs 0 and 2 only
```

### CPU Allowance (Time-based)

```bash
# Set CPU time allowance (percentage)
incus config set <container> limits.cpu.allowance 50%     # 50% of CPU time
incus config set <container> limits.cpu.allowance 200%    # 200% (2 full CPUs)

# With specific CPU count
incus config set <container> limits.cpu 2
incus config set <container> limits.cpu.allowance 50%     # 50% of 2 CPUs
```

### Remove CPU Limits

```bash
incus config unset <container> limits.cpu
incus config unset <container> limits.cpu.allowance
```

## Storage Limits

### Setting Root Disk Size Limits

```bash
# Set storage limit on root device
incus config device set <container> root size <size>

# Examples
incus config device set haproxy root size 20GiB    # 20 gigabytes
incus config device set mgo root size 200GiB       # 200 gigabytes
incus config device set motd root size 50GB        # 50 gigabytes
```

### Storage Size Units
Same as memory units: `B`, `KB`/`KiB`, `MB`/`MiB`, `GB`/`GiB`, `TB`/`TiB`

### Check Current Storage Device Config

```bash
# Show all devices
incus config device show <container>

# Check specific device size
incus config device get <container> root size
```

### Remove Storage Limit

```bash
incus config device unset <container> root size
```

## Practical Examples

### Example 1: Web Server Container
```bash
# Create container with balanced resources
incus launch images:debian/12 webserver
incus config set webserver limits.memory 2GiB
incus config set webserver limits.cpu 2
incus config device set webserver root size 50GiB
```

### Example 2: Database Server Container
```bash
# Database needs more memory and storage
incus launch images:debian/12 database
incus config set database limits.memory 8GiB
incus config set database limits.cpu 4
incus config device set database root size 500GiB
```

### Example 3: Development Container
```bash
# Development environment with moderate resources
incus launch images:alpine/3.18 devbox
incus config set devbox limits.memory 4GiB
incus config set devbox limits.cpu 2
incus config device set devbox root size 100GiB
```

## Best Practices

### 1. Start Conservative
Begin with lower limits and increase as needed based on actual usage patterns.

### 2. Monitor Usage
Regularly check resource usage with `incus-stats` to ensure limits are appropriate.

### 3. Leave Headroom
Set limits 20-30% higher than typical usage to handle spikes.

### 4. Consider Container Purpose
- **Web servers**: Moderate CPU, moderate memory, variable storage
- **Databases**: High memory, moderate CPU, high storage
- **Load balancers**: Low memory, moderate CPU, minimal storage
- **Development**: Flexible limits based on projects

### 5. Memory Recommendations by Service Type
- HAProxy/Nginx (proxy): 256MiB - 1GiB
- MySQL/MariaDB: 2GiB - 8GiB minimum
- Web applications: 512MiB - 4GiB
- Mail servers: 1GiB - 4GiB

## Viewing All Limits

### Check All Resource Limits
```bash
# Show all configuration including limits
incus config show <container> | grep -E "limits\.|size:"

# Show in YAML format
incus config show <container> --expanded
```

### Quick Commands Reference
```bash
# Memory
incus config get <container> limits.memory
incus config set <container> limits.memory 2GiB
incus config unset <container> limits.memory

# CPU
incus config get <container> limits.cpu
incus config set <container> limits.cpu 4
incus config unset <container> limits.cpu

# Storage
incus config device get <container> root size
incus config device set <container> root size 100GiB
incus config device unset <container> root size
```

## Troubleshooting

### Error: "Device from profile(s) cannot be modified"
This occurs when the device (usually `root`) is inherited from a profile rather than defined on the container.

**Solution 1: Override the device**
```bash
# Override the device from the profile
incus config device override <container> root

# Then set the size
incus config device set <container> root size 50GiB
```

**Solution 2: Add the device directly**
```bash
# Add root device with size
incus config device add <container> root disk pool=default path=/ size=50GiB
```

### Container Hitting Memory Limit
Symptoms: Out of memory errors, processes killed
```bash
# Check current usage vs limit
incus info <container> | grep -A5 "Memory"

# Increase limit
incus config set <container> limits.memory 4GiB
```

### Storage Full
Symptoms: Write errors, cannot create files
```bash
# Check disk usage
incus exec <container> -- df -h

# Increase storage limit
incus config device set <container> root size 100GiB
```

### CPU Throttling
Symptoms: Slow performance, high wait times
```bash
# Check CPU usage
incus info <container> | grep -A5 "CPU"

# Increase CPU allocation
incus config set <container> limits.cpu 4
```

## Integration with NetServa

The `incus-stats` command is part of the NetServa management suite and provides a quick overview of all container resources:

```bash
# Add to PATH for easy access
echo 'export PATH="$PATH:$HOME/.ns/bin"' >> ~/.bashrc
source ~/.bashrc

# Run from anywhere
incus-stats
```

## Related Commands

- `incus list` - List all containers
- `incus info <container>` - Detailed container information
- `incus config show <container>` - Show container configuration
- `incus-stats` - NetServa resource usage summary

## References

- [Incus Documentation](https://linuxcontainers.org/incus/docs/main/)
- [Resource Limits Guide](https://linuxcontainers.org/incus/docs/main/reference/instance_options/#resource-limits)
- NetServa Container Management: `man incus-stats`