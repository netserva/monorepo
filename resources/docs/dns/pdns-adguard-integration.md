# PowerDNS + AdGuard Home Integration on OpenWrt

## Overview
This guide explains how to integrate AdGuard Home with PowerDNS on OpenWrt to achieve both ad blocking and split DNS functionality.

## Architecture Design

### Recommended: AdGuard → PowerDNS Chain
```
[Clients] 
    ↓ (port 53)
[AdGuard Home] 
    ↓ (port 5301)
[PowerDNS Recursor]
    ├→ [PowerDNS Authoritative] (port 5300) - Internal domains
    └→ [Upstream DNS] - External domains
```

This architecture provides:
- **Ad blocking** for all DNS queries (internal and external)
- **Split DNS** for internal domain resolution
- **Caching** at multiple levels for performance
- **Privacy** through AdGuard's DNS-over-HTTPS/TLS support

## Installation & Configuration

### Step 1: Install AdGuard Home
```bash
# When package repository is available:
opkg update
opkg install adguardhome
```

### Step 2: Reconfigure PowerDNS Recursor
Move PowerDNS Recursor from port 53 to 5301:

```bash
# Edit /etc/powerdns/recursor.conf
cat > /etc/powerdns/recursor.conf << 'EOF'
local-address=127.0.0.1
local-port=5301
allow-from=127.0.0.0/8,192.168.1.0/24
forward-zones=goldcoast.org=192.168.1.1:5300,1.168.192.in-addr.arpa=192.168.1.1:5300,netserva.org=192.168.1.1:5300,netserva.com=192.168.1.1:5300,netserva.net=192.168.1.1:5300,illareen.net=192.168.1.1:5300,motd.com=192.168.1.1:5300,channie.net=192.168.1.1:5300,opensrc.org=192.168.1.1:5300
forward-zones-recurse=.=1.1.1.1;8.8.8.8;9.9.9.9
max-cache-entries=100000
dnssec=off
threads=2
socket-dir=/var/run/pdns-recursor
EOF

# Restart recursor
killall pdns_recursor
pdns_recursor --config-dir=/etc/powerdns --daemon
```

### Step 3: Configure AdGuard Home
Create AdGuard Home configuration:

```yaml
# /etc/adguardhome/AdGuardHome.yaml
bind_host: 192.168.1.1
bind_port: 53
users: []
http:
  bind_host: 192.168.1.1
  bind_port: 3000
dns:
  bind_host: 192.168.1.1
  port: 53
  upstream_dns:
    - 127.0.0.1:5301  # PowerDNS Recursor for all queries
  bootstrap_dns:
    - 1.1.1.1
    - 8.8.8.8
  filtering_enabled: true
  blocking_mode: default
  blocked_response_ttl: 10
  parental_enabled: false
  safesearch_enabled: false
  safebrowsing_enabled: true
  cache_size: 4194304
  cache_ttl_min: 0
  cache_ttl_max: 0
  bogus_nxdomain: []
  aaaa_disabled: false
  enable_dnssec: false
  edns_client_subnet: false
  custom_blocking_ipv4: ""
  custom_blocking_ipv6: ""
  
# Block lists
filters:
  - enabled: true
    url: https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts
    name: StevenBlack's Unified Hosts
  - enabled: true
    url: https://someonewhocares.org/hosts/zero/hosts
    name: Dan Pollock's List
  - enabled: true
    url: https://raw.githubusercontent.com/AdguardTeam/AdguardFilters/master/BaseFilter/sections/adservers.txt
    name: AdGuard Base Filter

# Custom filtering rules for local domains
user_rules:
  - "@@||goldcoast.org^"  # Whitelist internal domain
  - "@@||netserva.org^"   # Whitelist internal domain
  - "@@||motd.com^"       # Whitelist internal domain
```

### Step 4: Start Services in Order
```bash
# 1. Start PowerDNS Authoritative (port 5300)
pdns_server --config-dir=/etc/powerdns --config-name=pdns --daemon

# 2. Start PowerDNS Recursor (port 5301)
pdns_recursor --config-dir=/etc/powerdns --daemon

# 3. Start AdGuard Home (port 53)
/etc/init.d/adguardhome start
/etc/init.d/adguardhome enable
```

### Step 5: Update Service Dependencies
Ensure services start in correct order at boot:

```bash
# Edit /etc/init.d/adguardhome
# Add to START section:
# START=65  # After PowerDNS (60)
```

## Testing the Setup

### 1. Test Ad Blocking
```bash
# Should be blocked
dig @192.168.1.1 doubleclick.net
# Result: 0.0.0.0 or NXDOMAIN

# Should resolve normally
dig @192.168.1.1 google.com
# Result: Actual Google IP
```

### 2. Test Split DNS (Internal Domains)
```bash
# Internal domain - resolved by PowerDNS
dig @192.168.1.1 pve1.goldcoast.org
# Result: 192.168.1.21

# External domain with internal IP
dig @192.168.1.1 mail.netserva.org
# Result: 192.168.1.248
```

### 3. Test Reverse DNS
```bash
dig @192.168.1.1 -x 192.168.1.254
# Result: haproxy.goldcoast.org
```

## Port Summary
| Service | Port | Interface | Purpose |
|---------|------|-----------|---------|
| AdGuard Home Web | 3000 | 192.168.1.1 | Admin interface |
| AdGuard Home DNS | 53 | 192.168.1.1 | Client queries |
| PowerDNS Recursor | 5301 | 127.0.0.1 | Recursive resolver |
| PowerDNS Auth | 5300 | 192.168.1.1 | Authoritative server |
| PowerDNS API | 8081 | 0.0.0.0 | REST API |

## Alternative: Selective Ad Blocking

If you only want ad blocking for external queries (keeping internal queries fast):

```yaml
# AdGuard upstream configuration
upstream_dns:
  - "[/goldcoast.org/]127.0.0.1:5301"  # Internal direct to recursor
  - "[/netserva.org/]127.0.0.1:5301"   # Internal direct to recursor
  - "[/motd.com/]127.0.0.1:5301"       # Internal direct to recursor
  - "1.1.1.1"  # External with ad blocking
  - "8.8.8.8"  # External with ad blocking
```

## Benefits of Integration

1. **Ad Blocking**: Blocks ads, trackers, and malware at network level
2. **Split DNS**: Internal domains resolve to internal IPs
3. **Performance**: Multiple caching layers
4. **Privacy**: DNS-over-HTTPS/TLS support via AdGuard
5. **Statistics**: AdGuard provides query logs and statistics
6. **Parental Controls**: Optional content filtering
7. **API Management**: PowerDNS API still available for DNS record management

## Troubleshooting

### Services Not Starting
```bash
# Check port conflicts
netstat -tulnp | grep -E ":(53|5300|5301|3000|8081)"

# Check logs
logread | grep -E "(adguard|pdns|recursor)"
```

### DNS Resolution Issues
```bash
# Test each component
dig @127.0.0.1 -p 5301 google.com  # Test recursor
dig @192.168.1.1 -p 5300 gw.goldcoast.org  # Test authoritative
dig @192.168.1.1 google.com  # Test full chain
```

### Cache Issues
```bash
# Flush AdGuard cache (via web UI or API)
curl -X POST http://192.168.1.1:3000/control/clear_cache

# Restart recursor to clear cache
killall pdns_recursor && pdns_recursor --config-dir=/etc/powerdns --daemon
```

## Web Interface Access

Once AdGuard Home is running:
1. Navigate to: `http://192.168.1.1:3000`
2. Complete initial setup wizard
3. Configure upstream DNS: `127.0.0.1:5301`
4. Enable desired blocklists
5. Add custom rules for internal domains

## Notes

- AdGuard Home handles DHCP if needed (can replace dnsmasq completely)
- Consider memory usage on limited routers (AdGuard uses ~50-150MB)
- Regular blocklist updates require internet connectivity
- AdGuard statistics can help identify problematic queries
- Can integrate with Home Assistant for monitoring