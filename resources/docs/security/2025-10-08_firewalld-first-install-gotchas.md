# Firewalld First Install Gotchas

## The Problem: Services Suddenly "Filtered" After Fresh Firewalld Install

When firewalld is installed for the first time on a system with running services, it immediately activates with a highly restrictive default policy that **only allows SSH (port 22) and DHCPv6-client**. All other services become "filtered" and inaccessible, even if they were working perfectly before.

## Real-World Case Study: 4-Hour Debugging Session

### The Symptoms
- Mail server containers (mgo: 192.168.1.244, motd: 192.168.1.250) suddenly inaccessible
- nmap showing all services as "filtered" except SSH
- Services running normally inside containers (`netstat -tuln` showed all ports listening)
- Container iptables wide open (all ACCEPT policies)

### The Investigation Journey
1. **Hour 1**: Suspected DNS issues - checked PowerDNS, split-DNS configuration
2. **Hour 2**: Investigated network routing and Incus container networking
3. **Hour 3**: Examined host-level firewalls and container management
4. **Hour 4**: `firewall-cmd --list-all` revealed the simple truth

### The Root Cause Discovery
Timeline analysis revealed:
- **2025-08-22 15:01** - WireGuard tools installed
- **2025-08-22 18:08** - Firewalld installed (3 hours later, likely for VPN security)
- **2025-08-23** - Services discovered inaccessible

**Key insight**: Installing firewalld for WireGuard security triggered the restrictive default policy.

### The Simple Fix
```bash
# Check current firewall status
firewall-cmd --list-all

# Add required services permanently
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https  
firewall-cmd --permanent --add-service=smtp
firewall-cmd --permanent --add-service=smtps
firewall-cmd --permanent --add-service=imap
firewall-cmd --permanent --add-service=imaps

# Add custom ports (e.g., Sieve)
firewall-cmd --permanent --add-port=4190/tcp

# Reload to apply changes
firewall-cmd --reload

# Verify configuration
firewall-cmd --list-all
```

## Understanding Nmap's "Filtered" Port Types

The original nmap scan showed:
```
989 filtered tcp ports (no-response)
10 filtered tcp ports (admin-prohibited)
```

### Admin-Prohibited vs No-Response
- **Admin-prohibited**: Firewall actively rejects with ICMP Type 3 Code 13 (immediate feedback)
- **No-response**: Firewall silently drops packets (timeout-based detection)

### Why Some Ports Get Different Treatment
The 10 admin-prohibited ports likely represent high-risk services:
- **Windows SMB/NetBIOS** (ports 135, 137-139, 445)
- **Legacy protocols** (FTP, Telnet, SNMP)
- **Database services** (MS SQL Server port 1433)

Explicit rejection provides immediate feedback for troubleshooting while maintaining security.

## Firewalld Default Behavior Explained

### Security-First Design Philosophy
Firewalld follows the principle of **explicit allow, implicit deny**:
- New installations start with minimal allowed services
- Services must be explicitly added to be accessible
- This prevents accidental exposure of services

### Default Zone Configuration
Fresh firewalld installation allows only:
- **SSH (22/tcp)** - For remote administration
- **DHCPv6-client** - For IPv6 network configuration

Everything else is blocked until explicitly permitted.

## Prevention and Best Practices

### Before Installing Firewalld
1. **Document current services**: `netstat -tuln` or `ss -tuln`
2. **Plan service allowlist**: Identify which services need external access
3. **Prepare firewall rules**: Have your `firewall-cmd` commands ready

### Immediate Post-Install Checklist
```bash
# 1. Check what's currently allowed
firewall-cmd --list-all

# 2. Add your essential services
firewall-cmd --permanent --add-service={http,https,smtp,smtps,imap,imaps}

# 3. Add custom ports
firewall-cmd --permanent --add-port=4190/tcp

# 4. Reload and verify
firewall-cmd --reload && firewall-cmd --list-all

# 5. Test connectivity
nmap -Pn localhost
```

### Zone-Based Configuration
Consider using different zones for different network interfaces:
```bash
# List available zones
firewall-cmd --get-zones

# Assign interface to specific zone
firewall-cmd --permanent --zone=trusted --change-interface=eth0

# Configure zone-specific rules
firewall-cmd --permanent --zone=public --add-service=https
firewall-cmd --permanent --zone=trusted --add-service=ssh
```

## Common Gotchas and Solutions

### Container Networking
- **Incus/LXC containers**: Firewalld on the container affects container services
- **Docker containers**: Firewalld can interfere with Docker's iptables rules
- **Port forwarding**: May require specific zones or rich rules

### Service Dependencies
Some services require multiple ports:
```bash
# Mail server typically needs
firewall-cmd --permanent --add-service=smtp      # 25/tcp
firewall-cmd --permanent --add-service=smtps     # 465/tcp  
firewall-cmd --permanent --add-service=imap      # 143/tcp
firewall-cmd --permanent --add-service=imaps     # 993/tcp
firewall-cmd --permanent --add-port=4190/tcp     # Sieve

# Web server typically needs
firewall-cmd --permanent --add-service=http      # 80/tcp
firewall-cmd --permanent --add-service=https     # 443/tcp
```

### Debugging Tools
```bash
# Check detailed firewall status
firewall-cmd --list-all-zones

# Monitor firewall logs
journalctl -u firewalld -f

# Test specific ports
nmap -Pn -p 80,443,25,993 localhost

# Check service definitions
firewall-cmd --info-service=http
```

## The Learning Experience

### What We Learned
1. **Firewalld is aggressive by default** - this is good security practice
2. **Always check firewall status first** when troubleshooting connectivity
3. **Installation timing matters** - correlating package install dates reveals causation
4. **Service documentation is crucial** - knowing what ports your services need

### Time Investment vs Knowledge Gained
While the 4-hour debugging session was initially frustrating, it provided:
- **Deep understanding** of firewalld architecture and default behavior  
- **Network troubleshooting methodology** from DNS through application layers
- **Container networking insights** and host/container security boundaries
- **Institutional knowledge** to prevent similar issues in the future

### The Silver Lining
This "expensive lesson" transformed into valuable operational knowledge:
- Future firewalld installations will include immediate service configuration
- Network troubleshooting now includes firewall status as a primary check
- Documentation exists to help others avoid the same 4-hour debugging session

## Key Takeaways

1. **Firewalld blocks everything by default** - plan for this during installation
2. **Check firewall status early** in any connectivity troubleshooting
3. **Document your service requirements** before major security changes  
4. **Test connectivity immediately** after firewall configuration changes
5. **The restrictive default is a feature, not a bug** - it forces explicit security decisions

Remember: The goal isn't to blame firewalld for being secure by default, but to understand and work with its security-first philosophy effectively.