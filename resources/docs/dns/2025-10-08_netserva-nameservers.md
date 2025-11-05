# NetServa Nameserver Infrastructure Documentation

**Created**: 2025-09-26 - **Updated**: 2025-09-26
**Copyright**: (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)

## Overview

NetServa operates a high-availability DNS infrastructure using PowerDNS with Primary/Secondary replication across multiple geographic locations. This document covers the complete nameserver setup, domain migration processes, and troubleshooting procedures.

## Infrastructure Architecture

### DNS Server Configuration
- **Primary**: ns1.goldcoast.org (119.42.55.148) - PowerDNS with MariaDB backend
- **Secondary 1**: ns2.goldcoast.org (175.45.182.28) - PowerDNS SLAVE replication
- **Secondary 2**: ns3.goldcoast.org (103.16.131.18) - PowerDNS SLAVE replication

### Geographic Distribution
- **Primary Location**: Gold Coast, Australia
- **Network Provider**: High-availability hosting with redundant connections
- **Replication**: Real-time AXFR transfers between primary and secondaries

### Service Components
- **DNS Software**: PowerDNS Authoritative Server 4.x
- **Database Backend**: MariaDB 10.x (Primary only)
- **Replication Method**: AXFR (DNS Zone Transfer)
- **Monitoring**: Automated health checks and alerting

## Domain Migration Process

### Automated Migration Script

The `migrate-zone` script provides complete automation for domain migrations:

```bash
# Basic usage
migrate-zone DOMAIN FROM_NS TO_NS

# Example: Migrate from kdebian.net infrastructure to goldcoast.org
migrate-zone eth-os.com 120.88.117.136 ns1.goldcoast.org
```

### Migration Process Steps

1. **AXFR Retrieval**: Performs zone transfer from source nameserver
2. **Zone Creation**: Creates MASTER zone on ns1.goldcoast.org with updated records
3. **Slave Configuration**: Adds SLAVE zones on ns2gc and ns3gc for replication
4. **Nameserver Update**: Updates registrar nameservers via SynergyWholesale API
5. **Verification**: Tests DNS resolution on all three nameservers

### Migration Script Features

- **Automatic Record Processing**: Handles A, MX, TXT, NS, SOA record types
- **DNSSEC Stripping**: Removes RRSIG, DNSKEY, NSEC records during migration
- **Serial Increment**: Updates SOA serial numbers for proper zone versioning
- **Nameserver Substitution**: Updates NS records to goldcoast.org infrastructure
- **Error Handling**: Comprehensive validation and rollback capabilities

### Supported Record Types

| Record Type | Processing | Notes |
|-------------|------------|--------|
| SOA | Updated | Nameserver changed to ns1.goldcoast.org, serial incremented |
| NS | Replaced | All NS records point to ns1/2/3.goldcoast.org |
| A/AAAA | Preserved | IP addresses maintained exactly |
| MX | Preserved | Mail routing maintained (often updated to mail.goldcoast.org) |
| TXT | Preserved | SPF, DMARC, DKIM records maintained |
| CNAME | Preserved | Alias records maintained |
| DNSSEC | Stripped | RRSIG, DNSKEY, NSEC records removed |

## SynergyWholesale API Integration

### API Configuration

The migration system integrates with SynergyWholesale's domain management API:

- **Endpoint**: `https://api.synergywholesale.com/server.php`
- **Method**: SOAP/XML POST requests
- **Authentication**: Reseller ID + API Key
- **Function**: `updateNameServers`

### Critical API Troubleshooting

**🚨 IMPORTANT**: The correct API endpoint is crucial for successful domain migrations:

- **❌ WRONG**: `http://manage.synergywholesale.com/wapi/domains/UpdateNameServers`
- **✅ CORRECT**: `https://api.synergywholesale.com/server.php`

Using the wrong endpoint will result in **403 Forbidden** errors that can halt bulk migration operations.

### API Request Format

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/">
    <soap:Body>
        <updateNameServers>
            <request>
                <resellerID>RESELLER_ID</resellerID>
                <apiKey>API_KEY</apiKey>
                <domainName>example.com</domainName>
                <nameServers SOAP-ENC:arrayType="xsd:string[3]" xsi:type="SOAP-ENC:Array">
                    <item xsi:type="xsd:string">ns1.goldcoast.org</item>
                    <item xsi:type="xsd:string">ns2.goldcoast.org</item>
                    <item xsi:type="xsd:string">ns3.goldcoast.org</item>
                </nameServers>
            </request>
        </updateNameServers>
    </soap:Body>
</soap:Envelope>
```

### API Success Response

```xml
<status xsi:type="xsd:string">OK</status>
<errorMessage xsi:type="xsd:string">Domain name servers have been updated</errorMessage>
```

## Bulk Migration for Customer Domains

### Migration Pipeline Status

✅ **Infrastructure Ready**: All nameservers operational and tested
✅ **API Integration Working**: SynergyWholesale API endpoint validated
✅ **Migration Script Tested**: Successfully migrated 7 test domains
✅ **Bulk Process Verified**: Ready for hundreds of customer domains

### Tested Domain Migrations

The following domains have been successfully migrated and are fully operational:

1. **kanary.org** - Initial test migration ✅
2. **kdebian.net** - API integration verification ✅  
3. **eth-os.com** - Bulk process testing ✅
4. **eth-os.net** - Bulk process testing ✅
5. **eth-os.org** - Bulk process testing ✅
6. **netserva.org** - Bulk process testing ✅
7. **netserva.com** - Bulk process testing ✅
8. **netserva.net** - Bulk process testing ✅

### Production Migration Recommendations

1. **Batch Processing**: Migrate domains in batches of 50-100 to manage API rate limits
2. **Monitoring**: Real-time monitoring of DNS resolution during migration
3. **Rollback Capability**: Maintain ability to quickly restore original nameservers
4. **Progress Tracking**: Log all successful and failed migrations for reporting

## PowerDNS Configuration

### Primary Server (ns1gc)

**Database Schema**:
```sql
-- Core tables
CREATE TABLE domains (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE KEY,
    type VARCHAR(8) NOT NULL,
    master VARCHAR(128) DEFAULT NULL
);

CREATE TABLE records (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(10) NOT NULL,
    content VARCHAR(65535) NOT NULL,
    ttl INT DEFAULT 3600,
    prio INT DEFAULT NULL
);
```

### Supermaster Configuration

Automatic slave zone creation is configured via the `supermasters` table:

```sql
INSERT INTO supermasters (ip, nameserver, account) VALUES
('119.42.55.148', 'ns1.goldcoast.org', 'goldcoast');
```

This enables automatic SLAVE zone creation when new zones are added to the primary.

## Common Issues and Solutions

### Issue: 403 Forbidden API Error
**Root Cause**: Wrong SynergyWholesale API endpoint
**Solution**: Ensure scripts use `https://api.synergywholesale.com/server.php`

### Issue: DNS Resolution Delays
**Root Cause**: DNS propagation and caching
**Solution**: Allow 24-48 hours for global DNS cache expiration

### Issue: Secondary Server Sync Failures
**Root Cause**: AXFR configuration or network connectivity
**Solution**: Verify allow-axfr-ips and firewall rules

### Issue: Database Connection Errors
**Root Cause**: PowerDNS database connectivity
**Solution**: Check credentials and database server status

## Migration Script Usage

### Individual Domain Migration
```bash
# Migrate single domain with automatic DNSSEC
migrate-zone example.com 120.88.117.136 ns1.goldcoast.org
```

### Bulk Migration Example
```bash
# Bulk migration script
domains=("domain1.com" "domain2.net" "domain3.org")
for domain in "${domains[@]}"; do
    migrate-zone "$domain" "120.88.117.136" "ns1.goldcoast.org"
    sleep 5  # Rate limiting
done
```

### Migration Monitoring
```bash
# Verify domain resolution on all nameservers
dig @ns1.goldcoast.org example.com SOA
dig @ns2.goldcoast.org example.com SOA
dig @ns3.goldcoast.org example.com SOA
```

## DNSSEC Implementation

### Overview
NetServa DNS infrastructure is **fully DNSSEC-enabled** with automatic signing for all migrated domains. This provides cryptographic security and validation for DNS responses.

### DNSSEC Configuration
- **Algorithm**: ECDSA P-256 (Algorithm 13) for optimal security and performance
- **Key Type**: Combined Signing Key (CSK) with flags 257
- **Signature Validity**: 21 days with automatic renewal
- **NSEC**: Used for authenticated denial of existence

### PowerDNS DNSSEC Setup
```bash
# DNSSEC is enabled on all three nameservers
# Configuration in /etc/powerdns/pdns.conf:
gmysql-dnssec=yes

# Required database tables (automatically created):
# - cryptokeys: DNSSEC key storage
# - domainmetadata: Zone metadata
# - tsigkeys: Transaction signatures
```

### DNSSEC Management Commands

#### Enable DNSSEC for Domain
```bash
# Automatic DNSSEC enabling (recommended)
enable-dnssec example.com

# Manual PowerDNS commands
pdnsutil secure-zone example.com
pdnsutil show-zone example.com
```

#### Verify DNSSEC Status
```bash
# Check DNSSEC signatures
dig @ns1.goldcoast.org example.com DNSKEY +dnssec
dig example.com A +dnssec

# Bypass DNSSEC validation for testing
dig example.com A +cd +short
```

#### DS Record Management
```bash
# Get DS records for registrar update
pdnsutil show-zone example.com | grep "^DS ="

# Example DS record output:
# DS = example.com. IN DS 29269 13 2 87b33f8...
# DS = example.com. IN DS 29269 13 4 760af29...
```

### Automated DS Record Management via SynergyWholesale API

NetServa now provides **full API automation** for DS record lifecycle management:

#### **API-Powered DNSSEC Tools:**
- `dnssec-list-ds domain.com` - List current DS records with UUIDs for removal
- `dnssec-remove-ds domain.com UUID` - Remove specific DS records by UUID
- `dnssec-add-ds domain.com keytag algorithm digest` - Add new DS records
- `dnssec-sync domain.com` - Synchronize PowerDNS keys with registrar

#### **Automated Migration & Setup:**
1. **`migrate-zone`** - Automatically enables DNSSEC and syncs DS records during domain migration
2. **`enable-dnssec`** - Attempts API sync first, falls back to manual instructions if needed

#### **Manual Fallback Process (if API fails):**
1. **Get DS Records**: Use `enable-dnssec` or `pdnsutil show-zone` command
2. **Access Control Panel**: Log into SynergyWholesale control panel
3. **Navigate to DNSSEC**: Find domain's DNSSEC/Security settings
4. **Replace DS Records**: Update with new Key Tag, Algorithm, and Digests
5. **Propagation**: Wait 24-48 hours for global DNS propagation

### DNSSEC Troubleshooting

#### Common DNSSEC Issues
| Issue | Cause | Solution |
|-------|-------|----------|
| Resolution fails | Old DS records at registrar | Update DS records manually |
| SERVFAIL responses | DNSSEC validation failure | Check DS record match |
| No DNSKEY records | PowerDNS DNSSEC disabled | Verify `gmysql-dnssec=yes` |

#### Diagnostic Commands
```bash
# Test DNSSEC validation
dig example.com A +dnssec @8.8.8.8

# Check DNSSEC chain
delv example.com A

# Validate specific nameserver
dig @ns1.goldcoast.org example.com DNSKEY +dnssec +multiline
```

### Automated Migration Features

The `migrate-zone` script now automatically:
- ✅ **Enables DNSSEC** for all newly migrated domains
- ✅ **Synchronizes DS records** via SynergyWholesale API
- ✅ **Removes old DS records** during domain migration
- ✅ **Validates signatures** on all three nameservers
- ✅ **Zero manual intervention** required for DNSSEC setup

### Production DNSSEC Status
- **Infrastructure**: ✅ All servers DNSSEC-ready with full API automation
- **Automation**: ✅ Complete DS record lifecycle management
- **Key Management**: ✅ CSK rotation configured with API sync
- **Monitoring**: ✅ DNSSEC validation tracking and automated sync
- **API Integration**: ✅ Full SynergyWholesale DNSSEC API support

## Support and Maintenance

### NetServa Support
- **Technical Contact**: Mark Constable <mc@netserva.org>
- **Infrastructure Monitoring**: 24/7 automated alerting
- **Emergency Response**: Critical issue escalation procedures

### Regular Maintenance
- **Database Backups**: Daily automated backups with integrity verification
- **Security Updates**: Monthly PowerDNS and system updates
- **Performance Monitoring**: Continuous query response time tracking
- **Capacity Planning**: Quarterly infrastructure scaling reviews

---

**Document Version**: 1.0  
**Last Updated**: 2025-09-26  
**Migration Status**: ✅ Production Ready for Customer Domains  
**Infrastructure Status**: ✅ All Systems Operational
