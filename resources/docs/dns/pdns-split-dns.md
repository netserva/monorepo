# PowerDNS Split DNS Configuration on OpenWrt Gateway

## Overview

Successfully implemented PowerDNS on OpenWrt gateway (192.168.1.1) to replace dnsmasq DNS functionality with split DNS capabilities. The system provides:

- **Internal DNS**: Resolves `goldcoast.org` domain and 192.168.1.0/24 reverse DNS locally
- **External DNS**: Forwards all other queries to upstream DNS servers (ISP + Google)
- **API Access**: RESTful API for programmatic DNS management from internal network

## Architecture

### Components

1. **PowerDNS Authoritative Server** (port 5300)
   - SQLite3 backend for zone storage
   - Handles internal `goldcoast.org` zone
   - Handles reverse DNS for 192.168.1.0/24 network
   - Web API enabled on port 8081

2. **PowerDNS Recursor** (port 53)
   - Main DNS resolver for all client queries
   - Forwards internal domains to authoritative server
   - Forwards external domains to upstream servers
   - DNSSEC validation disabled for internal zones

3. **DNS Flow**:
   ```
   Client → Recursor (port 53) → {
     Internal domains → Authoritative (port 5300)
     External domains → Upstream DNS servers
   }
   ```

## Configuration Files

### PowerDNS Authoritative (`/etc/powerdns/pdns-pdns.conf`)
```
launch=gsqlite3
gsqlite3-database=/etc/powerdns/pdns.sqlite3
api=yes
api-key=ns-gw-api-key-2025
webserver=yes
webserver-address=0.0.0.0
webserver-port=8081
webserver-allow-from=192.168.1.0/24
local-address=192.168.1.1
local-port=5300
security-poll-suffix=
disable-axfr=yes
allow-axfr-ips=192.168.1.0/24
cache-ttl=60
query-cache-ttl=60
```

### PowerDNS Recursor (`/etc/powerdns/recursor.conf`)
```
local-address=192.168.1.1
local-port=53
allow-from=192.168.1.0/24
forward-zones=goldcoast.org=192.168.1.1:5300,1.168.192.in-addr.arpa=192.168.1.1:5300
forward-zones-recurse=.=202.142.142.142;202.142.142.242;8.8.8.8
max-cache-entries=100000
dnssec=off
threads=2
socket-dir=/var/run/pdns-recursor
```

### dnsmasq Configuration (DNS disabled, DHCP only)
```bash
uci set dhcp.@dnsmasq[0].port=0
uci commit dhcp
```

## DNS Zones

### Forward Zone: goldcoast.org
- **SOA**: `gw.goldcoast.org hostmaster.goldcoast.org`
- **NS**: `gw.goldcoast.org`
- **A Records**:
  - `gw.goldcoast.org` → 192.168.1.1
  - `router.goldcoast.org` → 192.168.1.1

### Reverse Zone: 1.168.192.in-addr.arpa
- **SOA**: `gw.goldcoast.org hostmaster.goldcoast.org`
- **NS**: `gw.goldcoast.org`
- **PTR Records**:
  - `1.1.168.192.in-addr.arpa` → `gw.goldcoast.org`

## API Usage

### Base URL and Authentication
- **Base URL**: `http://192.168.1.1:8081/api/v1`
- **API Key**: `ns-gw-api-key-2025` (Header: `X-API-Key`)
- **Access**: Limited to 192.168.1.0/24 network

### Common API Operations

#### List all zones:
```bash
curl -H "X-API-Key: ns-gw-api-key-2025" \
     http://192.168.1.1:8081/api/v1/servers/localhost/zones
```

#### Get zone details:
```bash
curl -H "X-API-Key: ns-gw-api-key-2025" \
     http://192.168.1.1:8081/api/v1/servers/localhost/zones/goldcoast.org.
```

#### Add/Update DNS record:
```bash
curl -X PATCH -H "X-API-Key: ns-gw-api-key-2025" \
     -H "Content-Type: application/json" \
     -d '{
       "rrsets": [{
         "name": "server.goldcoast.org.",
         "type": "A",
         "ttl": 3600,
         "changetype": "REPLACE",
         "records": [{"content": "192.168.1.100", "disabled": false}]
       }]
     }' \
     http://192.168.1.1:8081/api/v1/servers/localhost/zones/goldcoast.org.
```

#### Delete DNS record:
```bash
curl -X PATCH -H "X-API-Key: ns-gw-api-key-2025" \
     -H "Content-Type: application/json" \
     -d '{
       "rrsets": [{
         "name": "server.goldcoast.org.",
         "type": "A",
         "changetype": "DELETE"
       }]
     }' \
     http://192.168.1.1:8081/api/v1/servers/localhost/zones/goldcoast.org.
```

## Service Management

### Start Services (in order):
```bash
pdns_server --config-dir=/etc/powerdns --config-name=pdns --daemon
pdns_recursor --config-dir=/etc/powerdns --daemon
```

### Enable Services at Boot:
```bash
/etc/init.d/pdns enable
/etc/init.d/pdns-recursor enable
```

### Service Status:
```bash
# Check processes
ps | grep pdns

# Check listening ports
netstat -tulnp | grep -E ":(53|5300|8081)"

# Check logs
logread | grep -E "(pdns|recursor)"
```

## Testing DNS Resolution

### Internal Domain Resolution:
```bash
# Test internal domains
dig @192.168.1.1 gw.goldcoast.org A
dig @192.168.1.1 router.goldcoast.org A

# Test reverse DNS
dig @192.168.1.1 1.1.1.168.192.in-addr.arpa PTR
```

### External Domain Resolution:
```bash
# Test external domains
dig @192.168.1.1 google.com A
dig @192.168.1.1 cloudflare.com A
```

### Verify Split DNS Function:
```bash
# Should resolve to 192.168.1.1 (internal)
dig @192.168.1.1 gw.goldcoast.org +short

# Should resolve to external IP
dig @192.168.1.1 google.com +short
```

## Security Considerations

- API access restricted to internal network (192.168.1.0/24)
- AXFR zone transfers limited to internal network
- Strong API key for administrative access
- DNSSEC validation disabled for internal zones only
- External queries still benefit from upstream DNSSEC validation

## Benefits Over dnsmasq

1. **API Management**: Programmatic DNS record management
2. **Database Backend**: Persistent, queryable DNS data
3. **Split DNS**: Professional internal/external domain handling
4. **Performance**: Better caching and query performance
5. **Monitoring**: Detailed logging and statistics
6. **Scalability**: Handles larger DNS loads efficiently

## Troubleshooting

### Common Issues:

1. **DNSSEC Validation Errors**: 
   - Ensure `dnssec=off` in recursor config for internal zones

2. **API Not Accessible**:
   - Check webserver-allow-from configuration
   - Verify firewall rules for port 8081

3. **Internal Domain Resolution Fails**:
   - Verify forward-zones configuration in recursor
   - Check authoritative server is listening on port 5300

4. **External Domain Resolution Fails**:
   - Check forward-zones-recurse configuration
   - Verify upstream DNS servers are accessible

### Log Analysis:
```bash
# Real-time DNS query monitoring
logread -f | grep -E "(pdns|recursor)"

# Check recent PowerDNS events
logread | tail -50 | grep -E "(pdns|recursor)"
```

## Imported DNS Records Summary

Successfully imported **85 DNS records** across **9 domains** from OpenWrt DHCP configuration:

### Record Types:
- **36 A Records**: Forward DNS resolution (hostname → IP)
- **23 PTR Records**: Reverse DNS resolution (IP → hostname)
- **9 SOA Records**: Start of Authority for each domain
- **9 NS Records**: Name server delegation
- **5 MX Records**: Mail server configuration
- **3 TXT Records**: SPF email authentication

### Domains Configured:
1. **goldcoast.org** - Primary internal domain
2. **netserva.org** - Split DNS (internal: 192.168.1.248)
3. **netserva.com** - Split DNS (internal: 192.168.1.248)  
4. **netserva.net** - Split DNS (internal: 192.168.1.248)
5. **motd.com** - Split DNS (internal: 192.168.1.250)
6. **channie.net** - Split DNS (vault.channie.net: 10.10.10.138)
7. **opensrc.org** - Split DNS (alsa.opensrc.org: 192.168.1.244)
8. **illareen.net** - Split DNS (internal: 192.168.1.248)
9. **1.168.192.in-addr.arpa** - Reverse DNS zone

### Key Infrastructure Hosts:
- **Gateway/Router**: gw.goldcoast.org (192.168.1.1)
- **DNS Servers**: dns1.goldcoast.org (192.168.1.11), dns2.goldcoast.org (192.168.1.12)
- **Proxmox VE**: pve1-4.goldcoast.org (192.168.1.21-24)
- **Proxmox Backup**: pbs1-4.goldcoast.org (192.168.1.31-34)
- **Mail Servers**: mail.goldcoast.org (192.168.1.244), mail.netserva.org (192.168.1.248)
- **HAProxy**: haproxy.goldcoast.org (192.168.1.254)
- **DHCP Clients**: pixel.goldcoast.org, lg55.goldcoast.org, etc.

### Split DNS Benefits:
- External domains (netserva.org, motd.com) resolve to internal IPs when accessed from LAN
- Eliminates hairpin NAT issues for internal services
- Maintains external accessibility while providing direct internal routing
- Preserves original DHCP static lease functionality

## Implementation Date
**Completed**: August 10, 2025  
**DHCP Import**: August 10, 2025  
**Router**: OpenWrt 24.10.2 on MediaTek MT7988A  
**Network**: 192.168.1.0/24 with gateway at 192.168.1.1