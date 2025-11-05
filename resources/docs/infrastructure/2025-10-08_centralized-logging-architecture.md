# Centralized Logging Architecture for NetServa Infrastructure

## Overview
Professional-grade centralized logging system for all NetServa servers using modern observability stack with Grafana as the visualization layer.

## Architecture Components

### 1. Log Collection Layer (All Servers)
- **syslog-ng** on each server configured to:
  - Collect local logs (system, services, applications)
  - Forward logs to central logging VM via TCP/TLS
  - Buffer logs locally during network outages
  - Tag logs with source server metadata

### 2. Central Logging VM Stack

#### Option A: Grafana Loki Stack (Recommended)
**Pros:** Lightweight, designed for logs, integrates perfectly with Grafana, cost-effective
**Components:**
- **Promtail/Vector** - Log collection agents
- **Loki** - Log aggregation and storage (like Prometheus but for logs)
- **Grafana** - Visualization and alerting
- **MinIO** (optional) - S3-compatible object storage for long-term retention

#### Option B: ELK Stack Alternative
**Pros:** More mature, powerful search, extensive plugin ecosystem
**Components:**
- **Elasticsearch** - Full-text search and analytics
- **Logstash/Fluentd** - Log processing pipeline
- **Kibana** - Native visualization (can still use Grafana)

### 3. Recommended Architecture: Grafana Loki Stack

```
┌─────────────────────────────────────────────────────────────┐
│                     All NetServa Servers                    │
├─────────────────────────────────────────────────────────────┤
│ nsorg │ motd │ haproxy │ mgo │ mx1 │ dns1 │ pve1 │ etc... │
│                                                             │
│    syslog-ng → Forward logs via TCP/TLS port 514/6514     │
└────────────────┬───────────────────────────────────────────┘
                 │
                 ↓ Encrypted TLS
┌────────────────────────────────────────────────────────────┐
│              Central Logging VM (e.g., logs.netserva.org)  │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ syslog-ng (Receiver)                                 │  │
│  │ - Listen on TCP 514 (internal) / TLS 6514 (external) │  │
│  │ - Parse and route logs by facility/source            │  │
│  │ - Forward to Promtail/Vector                         │  │
│  └────────────┬─────────────────────────────────────────┘  │
│               │                                             │
│               ↓                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Promtail/Vector (Log Shipper)                        │  │
│  │ - Parse syslog format                                │  │
│  │ - Add labels (host, service, facility, severity)    │  │
│  │ - Batch and compress                                 │  │
│  │ - Send to Loki                                       │  │
│  └────────────┬─────────────────────────────────────────┘  │
│               │                                             │
│               ↓                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Loki (Log Database)                                  │  │
│  │ - Index metadata only (labels)                       │  │
│  │ - Compress and store log chunks                      │  │
│  │ - Retention policies (30d hot, 90d warm, 1y cold)   │  │
│  │ - Query API for Grafana                             │  │
│  └────────────┬─────────────────────────────────────────┘  │
│               │                                             │
│               ↓                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Grafana (Visualization)                              │  │
│  │ - Log Explorer with LogQL queries                    │  │
│  │ - Dashboards per service/server                      │  │
│  │ - Alerting rules                                     │  │
│  │ - User authentication (LDAP/OAuth/local)             │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Optional: MinIO (Long-term Storage)                  │  │
│  │ - S3-compatible object storage                       │  │
│  │ - Archive logs older than 90 days                    │  │
│  │ - Compliance/audit trail                             │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
```

## Implementation Steps

### Phase 1: Prepare Central Logging VM
```bash
# Create Alpine VM/LXC (2 CPU, 4GB RAM, 100GB storage minimum)
# Hostname: logs.netserva.org

# Install required packages
apk add syslog-ng grafana loki promtail

# Or use Docker Compose for easier management
apk add docker docker-compose
```

### Phase 2: Configure syslog-ng on Each Server

#### Sender Configuration (on each server like nsorg)
```conf
# /etc/syslog-ng/syslog-ng.conf additions

# Define central log server destination
destination d_central {
    network("logs.netserva.org"
        port(6514)
        transport("tls")
        tls(
            ca-dir("/etc/syslog-ng/ca.d")
            key-file("/etc/syslog-ng/cert.d/client-key.pem")
            cert-file("/etc/syslog-ng/cert.d/client-cert.pem")
        )
        disk-buffer(
            mem-buf-size(10485760)  # 10MB memory buffer
            disk-buf-size(104857600) # 100MB disk buffer
            reliable(yes)
        )
    );
};

# Forward all logs to central server
log {
    source(s_src);
    destination(d_central);
    flags(flow-control);  # Prevent message loss
};

# Keep local copies
log {
    source(s_src);
    filter(f_mail);
    destination(d_mail);
};
```

#### Receiver Configuration (on central logging VM)
```conf
# /etc/syslog-ng/syslog-ng.conf on central server

source s_network {
    network(
        port(6514)
        transport("tls")
        tls(
            key-file("/etc/syslog-ng/cert.d/server-key.pem")
            cert-file("/etc/syslog-ng/cert.d/server-cert.pem")
            ca-dir("/etc/syslog-ng/ca.d")
            peer-verify(required-trusted)
        )
        max-connections(100)
        keep-hostname(yes)
    );
};

# Parse and tag logs
parser p_add_metadata {
    csv-parser(
        columns("FACILITY", "SEVERITY", "TIMESTAMP", "HOST", "PROGRAM", "PID", "MESSAGE")
        delimiters(" ")
        flags(escape-double-char)
    );
};

# Forward to Promtail
destination d_promtail {
    syslog("localhost" 
        port(1514)
        transport("tcp")
    );
};

log {
    source(s_network);
    parser(p_add_metadata);
    destination(d_promtail);
};
```

### Phase 3: Configure Loki and Promtail

#### Promtail Configuration
```yaml
# /etc/promtail/config.yml
server:
  http_listen_port: 9080
  grpc_listen_port: 0

positions:
  filename: /tmp/positions.yaml

clients:
  - url: http://localhost:3100/loki/api/v1/push

scrape_configs:
  - job_name: syslog
    syslog:
      listen_address: 0.0.0.0:1514
      labels:
        job: "syslog"
    relabel_configs:
      - source_labels: ['__syslog_message_hostname']
        target_label: 'host'
      - source_labels: ['__syslog_message_facility']
        target_label: 'facility'
      - source_labels: ['__syslog_message_severity']
        target_label: 'severity'
      - source_labels: ['__syslog_message_app_name']
        target_label: 'application'
```

#### Loki Configuration
```yaml
# /etc/loki/config.yml
auth_enabled: false

server:
  http_listen_port: 3100
  grpc_listen_port: 9096

common:
  path_prefix: /tmp/loki
  storage:
    filesystem:
      chunks_directory: /tmp/loki/chunks
      rules_directory: /tmp/loki/rules
  replication_factor: 1
  ring:
    instance_addr: 127.0.0.1
    kvstore:
      store: inmemory

schema_config:
  configs:
    - from: 2024-01-01
      store: boltdb-shipper
      object_store: filesystem
      schema: v11
      index:
        prefix: index_
        period: 24h

storage_config:
  boltdb_shipper:
    active_index_directory: /var/lib/loki/boltdb-shipper-active
    cache_location: /var/lib/loki/boltdb-shipper-cache
    cache_ttl: 24h
    shared_store: filesystem
  filesystem:
    directory: /var/lib/loki/chunks

limits_config:
  enforce_metric_name: false
  reject_old_samples: true
  reject_old_samples_max_age: 168h
  ingestion_rate_mb: 10
  ingestion_burst_size_mb: 20

chunk_store_config:
  max_look_back_period: 0s

table_manager:
  retention_deletes_enabled: true
  retention_period: 720h  # 30 days
```

### Phase 4: Configure Grafana

#### Grafana Data Source
```yaml
# Add Loki as data source in Grafana
apiVersion: 1
datasources:
  - name: Loki
    type: loki
    access: proxy
    url: http://localhost:3100
    jsonData:
      maxLines: 1000
```

#### Example Dashboard Queries (LogQL)

```logql
# All logs from a specific host
{host="nsorg"}

# Mail logs with errors
{facility="mail"} |= "error"

# SSH authentication failures
{application="sshd"} |= "Failed password"

# Nginx access logs with 5xx errors
{application="nginx"} |~ "5[0-9]{2}"

# Rate of errors per minute by host
sum(rate({severity="error"}[1m])) by (host)

# Top 10 applications by log volume
topk(10, sum(count_over_time({job="syslog"}[5m])) by (application))
```

## Security Considerations

1. **TLS Encryption**: All log forwarding uses TLS 1.3
2. **Certificate Management**: Use self-signed CA or Let's Encrypt
3. **Access Control**: 
   - Grafana authentication (LDAP/OAuth/local users)
   - Network firewall rules (only allow specific IPs)
   - Read-only access for most users
4. **Data Retention**: 
   - Hot storage: 30 days (fast SSD)
   - Warm storage: 90 days (slower disk)
   - Cold storage: 1+ years (object storage/archive)

## Resource Requirements

### Central Logging VM
- **CPU**: 2-4 cores (depending on log volume)
- **RAM**: 4-8 GB
- **Storage**: 
  - 100GB for OS and applications
  - 500GB-1TB for log storage (depends on retention)
- **Network**: 1Gbps recommended

### Estimated Log Volumes
- Small server: ~100MB/day
- Mail server: ~500MB/day
- Web server: ~1GB/day
- HAProxy: ~2GB/day

Total for 10 servers: ~10GB/day = 300GB/month

## Monitoring the Monitoring

### Health Checks
- Loki ingestion rate
- Promtail scrape errors
- Disk usage alerts
- Certificate expiration monitoring
- Network connectivity tests

### Backup Strategy
- Daily backup of Grafana dashboards/config
- Weekly backup of Loki index
- Archive old logs to object storage

## Alternative: Docker Compose Setup

```yaml
# docker-compose.yml for quick deployment
version: "3"

networks:
  loki:

services:
  loki:
    image: grafana/loki:latest
    ports:
      - "3100:3100"
    volumes:
      - ./loki-config.yaml:/etc/loki/local-config.yaml
      - loki-data:/loki
    command: -config.file=/etc/loki/local-config.yaml
    networks:
      - loki

  promtail:
    image: grafana/promtail:latest
    ports:
      - "1514:1514"
      - "1514:1514/udp"
    volumes:
      - ./promtail-config.yaml:/etc/promtail/config.yml
      - /var/log:/var/log:ro
    command: -config.file=/etc/promtail/config.yml
    networks:
      - loki

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    volumes:
      - grafana-data:/var/lib/grafana
      - ./grafana-datasources.yaml:/etc/grafana/provisioning/datasources/datasources.yaml
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
      - GF_USERS_ALLOW_SIGN_UP=false
    networks:
      - loki

volumes:
  loki-data:
  grafana-data:
```

## Next Steps

1. Set up the central logging VM
2. Install and configure the Loki stack
3. Configure syslog-ng on one test server (nsorg)
4. Verify logs are flowing to Grafana
5. Create initial dashboards
6. Roll out to all servers
7. Set up alerting rules
8. Document runbooks for common issues