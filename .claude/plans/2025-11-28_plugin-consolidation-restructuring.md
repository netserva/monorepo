# NetServa 3.0 Plugin Consolidation & Restructuring Plan

**Date:** 2025-11-28
**Status:** Planning
**Estimated Effort:** 40-60 hours across 9 phases

---

## Executive Summary

Consolidate 13 plugins into 8 cohesive packages while fixing critical architectural issues, standardizing patterns, and implementing Filament clusters for improved UX.

### Before → After

| Current (13) | Proposed (8) | Change |
|--------------|--------------|--------|
| core | **core** | +admin, +cli |
| admin | *(merged into core)* | - |
| cli | *(merged into core)* | - |
| fleet | **fleet** | +ipam, +wg |
| ipam | *(merged into fleet)* | - |
| wg | *(merged into fleet)* | - |
| dns | **dns** | unchanged |
| mail | **mail** | +MailCredential |
| web | **web** | unchanged |
| ops | **ops** | +cron |
| cron | *(merged into ops)* | - |
| config | **config** | -Database* (to fleet) |
| cms | **cms** | unchanged (standalone) |

---

## Critical Issues to Fix (From Audit)

### 1. BLOCKING: VConf Model Misplacement
- **Current:** `netserva-cli/src/Models/VConf.php`
- **Problem:** Fleet imports from CLI (inverse dependency)
- **Fix:** Move to `netserva-core/src/Models/Vhost/VConf.php`

### 2. BLOCKING: RemoteExecutionService Location
- **Current:** `netserva-cli/src/Services/RemoteExecutionService.php`
- **Problem:** Core SSH functionality isolated in CLI
- **Fix:** Move to `netserva-core/src/Services/Execution/RemoteExecutionService.php`

### 3. HIGH: MailCredential Misplacement
- **Current:** `netserva-cli/src/Models/MailCredential.php`
- **Problem:** Mail-specific model in CLI package
- **Fix:** Move to `netserva-mail/src/Models/MailCredential.php`

### 4. HIGH: Database Models in Wrong Package
- **Current:** `netserva-config/src/Models/Database*.php`
- **Problem:** Infrastructure resources masquerading as config
- **Fix:** Move to `netserva-fleet/src/Models/Database/`

### 5. MEDIUM: 5 Duplicate Credential Patterns
- MailCredential, FleetVhostCredential, DatabaseCredential, VPass, Secret
- **Fix:** Create unified `CredentialInterface` in Core

---

## Detailed Package Architecture

### 1. netserva-core (Foundation Layer)

**Absorbs:** admin, cli
**Total:** ~16 models, ~29 services, ~7 resources

```
packages/netserva-core/
├── composer.json
├── src/
│   ├── Console/Commands/
│   │   ├── Config/           # addcfg, chcfg, delcfg, shcfg
│   │   ├── Ssh/              # addssh, chssh, delssh, shssh
│   │   ├── Plugin/           # plugin:*, discover, enable, disable
│   │   ├── Setup/            # setup, migrate commands
│   │   └── Vhost/            # addvhost, chvhost, delvhost, shvhost, vconf*
│   ├── Contracts/
│   │   └── CredentialInterface.php  # Unified credential contract
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   └── SystemCluster.php    # Groups admin resources
│   │   └── Resources/
│   │       ├── AuditLogResource.php
│   │       ├── PluginResource.php
│   │       ├── SettingResource.php
│   │       ├── SetupTemplateResource.php
│   │       ├── SetupJobResource.php
│   │       ├── SetupComponentResource.php
│   │       └── MigrationJobResource.php
│   ├── Models/
│   │   ├── Ssh/
│   │   │   ├── SshHost.php
│   │   │   ├── SshKey.php
│   │   │   └── SshConnection.php
│   │   ├── Vhost/
│   │   │   ├── VConf.php              # MOVED from cli
│   │   │   └── VhostConfiguration.php # MOVED from cli
│   │   ├── Setup/
│   │   │   ├── SetupTemplate.php
│   │   │   ├── SetupJob.php
│   │   │   └── SetupComponent.php
│   │   ├── Migration/
│   │   │   └── MigrationJob.php
│   │   └── System/
│   │       ├── Setting.php
│   │       ├── AuditLog.php
│   │       ├── InstalledPlugin.php
│   │       └── BaseInfrastructureNode.php
│   ├── Services/
│   │   ├── Ssh/
│   │   │   ├── RemoteConnectionService.php
│   │   │   ├── SshTunnelService.php
│   │   │   └── SshConfigService.php      # MOVED from cli
│   │   ├── Execution/
│   │   │   ├── RemoteExecutionService.php # MOVED from cli (CRITICAL)
│   │   │   └── BashScriptBuilder.php      # MOVED from cli
│   │   ├── Vhost/
│   │   │   ├── VhostManagementService.php
│   │   │   ├── VhostValidationService.php
│   │   │   ├── VhostConfigService.php
│   │   │   ├── VhostRepairService.php
│   │   │   ├── VhostPermissionsService.php
│   │   │   └── VhostResolverService.php
│   │   ├── Setup/
│   │   │   ├── SetupService.php
│   │   │   ├── MigrationService.php
│   │   │   └── MigrationExecutionService.php
│   │   └── System/
│   │       ├── ConfigurationService.php
│   │       ├── DependencyResolver.php
│   │       ├── LoggingService.php
│   │       └── NotificationService.php
│   └── Providers/
│       └── CoreServiceProvider.php
└── database/migrations/
```

**Filament Cluster: System Administration**
- AuditLog, Plugin, Setting (from admin)
- SetupTemplate, SetupJob, SetupComponent, MigrationJob (from cli)

---

### 2. netserva-fleet (Infrastructure Layer)

**Absorbs:** ipam, wg, Database* models from config
**Total:** ~15 models, ~10 services, ~12 resources

```
packages/netserva-fleet/
├── src/
│   ├── Console/Commands/
│   │   ├── Fleet/            # venue, vsite, vnode, vhost commands
│   │   ├── Discovery/        # discover-* commands
│   │   ├── Dnsmasq/          # dnsmasq commands
│   │   ├── Ipam/             # IP management commands
│   │   └── Vpn/              # wireguard commands
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── InfrastructureCluster.php  # Venue, VSite, VNode, VHost
│   │   │   ├── IpManagementCluster.php    # Network, Address, Reservation
│   │   │   ├── VpnCluster.php             # WireguardServer, WireguardPeer
│   │   │   └── DatabaseCluster.php        # Database, Connection, Credential
│   │   └── Resources/
│   │       ├── Infrastructure/
│   │       │   ├── FleetVenueResource.php
│   │       │   ├── FleetVsiteResource.php
│   │       │   ├── FleetVnodeResource.php
│   │       │   └── FleetVhostResource.php
│   │       ├── Ipam/
│   │       │   ├── IpNetworkResource.php
│   │       │   ├── IpAddressResource.php
│   │       │   └── IpReservationResource.php
│   │       ├── Vpn/
│   │       │   ├── WireguardServerResource.php
│   │       │   └── WireguardPeerResource.php
│   │       └── Database/
│   │           ├── DatabaseResource.php       # MOVED from config
│   │           ├── DatabaseConnectionResource.php
│   │           └── DatabaseCredentialResource.php
│   ├── Models/
│   │   ├── Infrastructure/
│   │   │   ├── FleetVenue.php
│   │   │   ├── FleetVsite.php
│   │   │   ├── FleetVnode.php
│   │   │   ├── FleetVhost.php
│   │   │   ├── FleetVServ.php
│   │   │   ├── FleetVHostCredential.php
│   │   │   └── FleetDnsmasqHost.php
│   │   ├── Ipam/
│   │   │   ├── IpNetwork.php
│   │   │   ├── IpAddress.php
│   │   │   └── IpReservation.php
│   │   ├── Vpn/
│   │   │   ├── WireguardServer.php
│   │   │   └── WireguardPeer.php
│   │   └── Database/
│   │       ├── Database.php           # MOVED from config
│   │       ├── DatabaseConnection.php
│   │       └── DatabaseCredential.php
│   └── Services/
│       ├── Discovery/
│       │   └── FleetDiscoveryService.php
│       ├── Ipam/
│       │   ├── IpamService.php
│       │   ├── IpAddressService.php
│       │   └── SubnetService.php
│       ├── Vpn/
│       │   └── WireguardService.php
│       ├── Database/
│       │   └── DatabaseService.php    # MOVED from config
│       └── Ipv6PtrConfigurationService.php
└── database/migrations/
```

**Filament Clusters:**
1. **Infrastructure** - Venue, VSite, VNode, VHost (hierarchy)
2. **IP Management** - Network, Address, Reservation
3. **VPN** - WireguardServer, WireguardPeer
4. **Database** - Database, Connection, Credential

---

### 3. netserva-dns (Service Layer)

**No changes to package boundaries**
**Add:** Filament clusters

```
packages/netserva-dns/
├── src/
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── DnsCluster.php      # Zone, Record, Provider
│   │   │   └── DomainsCluster.php  # Registration, Registrar
│   │   └── Resources/
│   │       ├── Dns/
│   │       │   ├── DnsZoneResource.php
│   │       │   ├── DnsRecordResource.php
│   │       │   └── DnsProviderResource.php
│   │       └── Domains/
│   │           ├── DomainRegistrationResource.php
│   │           └── DomainRegistrarResource.php
```

**Filament Clusters:**
1. **DNS** - Zone, Record, Provider
2. **Domains** - Registration, Registrar

---

### 4. netserva-mail (Service Layer)

**Absorbs:** MailCredential from cli
**Add:** Filament clusters

```
packages/netserva-mail/
├── src/
│   ├── Console/Commands/
│   │   ├── Mailbox/          # addvmail, chvmail, delvmail, shvmail (from cli)
│   │   ├── Alias/            # addvalias, chvalias, delvalias, shvalias (from cli)
│   │   └── Operations/       # dkim, show-mail
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── MailConfigCluster.php     # Server, Domain, Mailbox, Alias
│   │   │   └── MailOperationsCluster.php # Queue, Log
│   │   └── Resources/
│   ├── Models/
│   │   ├── MailServer.php
│   │   ├── MailDomain.php
│   │   ├── Mailbox.php
│   │   ├── MailAlias.php
│   │   ├── MailQueue.php
│   │   ├── MailLog.php
│   │   └── MailCredential.php  # MOVED from cli
│   └── Services/
│       ├── MailService.php
│       ├── VmailManagementService.php  # MOVED from cli
│       └── DovecotPasswordService.php  # MOVED from cli
```

**Filament Clusters:**
1. **Configuration** - Server, Domain, Mailbox, Alias
2. **Operations** - Queue, Log

**Commands Moved from CLI:**
- addvmail, chvmail, delvmail, shvmail
- addvalias, chvalias, delvalias, shvalias
- UserPasswordCommand, UserShowCommand

---

### 5. netserva-web (Service Layer)

**No changes to package boundaries**
**Add:** Filament clusters

```
packages/netserva-web/
├── src/
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── WebServersCluster.php  # Server, VirtualHost, Application
│   │   │   └── SslCluster.php         # Certificate, Deployment, Authority
│   │   └── Resources/
```

**Filament Clusters:**
1. **Web Servers** - Server, VirtualHost, Application
2. **SSL/TLS** - Certificate, Deployment, Authority

---

### 6. netserva-ops (Operations Layer)

**Absorbs:** cron
**Add:** Filament clusters

```
packages/netserva-ops/
├── src/
│   ├── Console/Commands/
│   │   ├── Monitoring/       # monitoring:* commands
│   │   ├── Analytics/        # analytics:* commands
│   │   ├── Backup/           # backup commands (future)
│   │   └── Automation/       # automation/cron commands
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── MonitoringCluster.php   # Check, AlertRule, Incident, StatusPage
│   │   │   ├── BackupCluster.php       # Job, Repository, Snapshot
│   │   │   ├── AnalyticsCluster.php    # DataSource, Metric, Alert, Dashboard, Visualization
│   │   │   └── AutomationCluster.php   # AutomationJob, AutomationTask (from cron)
│   │   └── Resources/
│   │       ├── Monitoring/
│   │       │   ├── MonitoringCheckResource.php
│   │       │   ├── AlertRuleResource.php
│   │       │   ├── IncidentResource.php
│   │       │   └── StatusPageResource.php
│   │       ├── Backup/
│   │       │   ├── BackupJobResource.php
│   │       │   ├── BackupRepositoryResource.php
│   │       │   └── BackupSnapshotResource.php
│   │       ├── Analytics/
│   │       │   ├── AnalyticsDashboardResource.php
│   │       │   ├── AnalyticsMetricResource.php
│   │       │   ├── AnalyticsDataSourceResource.php
│   │       │   ├── AnalyticsVisualizationResource.php
│   │       │   └── AnalyticsAlertResource.php
│   │       └── Automation/
│   │           ├── AutomationJobResource.php   # FROM cron
│   │           └── AutomationTaskResource.php  # FROM cron
│   ├── Models/
│   │   ├── Monitoring/
│   │   ├── Backup/
│   │   ├── Analytics/
│   │   └── Automation/
│   │       ├── AutomationJob.php   # FROM cron
│   │       └── AutomationTask.php  # FROM cron
│   └── Services/
│       └── AutomationService.php   # FROM cron
```

**Filament Clusters:**
1. **Monitoring** - Check, AlertRule, Incident, StatusPage
2. **Backup** - Job, Repository, Snapshot
3. **Analytics** - DataSource, Metric, Alert, Dashboard, Visualization
4. **Automation** - AutomationJob, AutomationTask

---

### 7. netserva-config (Configuration Layer)

**Loses:** Database* models (to fleet)
**Keeps:** Templates, Secrets

```
packages/netserva-config/
├── src/
│   ├── Filament/
│   │   ├── Clusters/
│   │   │   ├── TemplatesCluster.php  # Template, Profile, Variable, Deployment
│   │   │   └── SecretsCluster.php    # Secret, SecretAccess
│   │   └── Resources/
│   │       ├── Templates/
│   │       │   ├── ConfigTemplateResource.php
│   │       │   ├── ConfigProfileResource.php
│   │       │   ├── ConfigVariableResource.php
│   │       │   └── ConfigDeploymentResource.php
│   │       └── Secrets/
│   │           ├── SecretResource.php
│   │           └── SecretAccessResource.php
│   ├── Models/
│   │   ├── Templates/
│   │   │   ├── ConfigTemplate.php
│   │   │   ├── ConfigProfile.php
│   │   │   ├── ConfigVariable.php
│   │   │   └── ConfigDeployment.php
│   │   └── Secrets/
│   │       ├── Secret.php
│   │       ├── SecretAccess.php
│   │       └── SecretCategory.php
│   └── Services/
│       ├── ConfigService.php
│       └── SecretsService.php
```

**Filament Clusters:**
1. **Templates** - Template, Profile, Variable, Deployment
2. **Secrets** - Secret, SecretAccess

---

### 8. netserva-cms (Content Layer - Standalone)

**No changes** - Already well-organized and standalone

---

## Navigation Structure (After Restructuring)

```
Sidebar (All Collapsed by Default)
├── Core (System Cluster)
│   ├── Settings
│   ├── Audit Logs
│   ├── Plugins
│   ├── Setup Templates
│   ├── Setup Jobs
│   ├── Setup Components
│   └── Migration Jobs
│
├── Fleet
│   ├── Infrastructure (Cluster)
│   │   ├── Venues
│   │   ├── VSites
│   │   ├── VNodes
│   │   └── VHosts
│   ├── IP Management (Cluster)
│   │   ├── Networks
│   │   ├── Addresses
│   │   └── Reservations
│   ├── VPN (Cluster)
│   │   ├── Servers
│   │   └── Peers
│   └── Database (Cluster)
│       ├── Databases
│       ├── Connections
│       └── Credentials
│
├── DNS
│   ├── DNS (Cluster)
│   │   ├── Zones
│   │   ├── Records
│   │   └── Providers
│   └── Domains (Cluster)
│       ├── Registrations
│       └── Registrars
│
├── Mail
│   ├── Configuration (Cluster)
│   │   ├── Servers
│   │   ├── Domains
│   │   ├── Mailboxes
│   │   └── Aliases
│   └── Operations (Cluster)
│       ├── Queue
│       └── Logs
│
├── Web
│   ├── Servers (Cluster)
│   │   ├── Web Servers
│   │   ├── Virtual Hosts
│   │   └── Applications
│   └── SSL (Cluster)
│       ├── Certificates
│       └── Deployments
│
├── Ops
│   ├── Monitoring (Cluster)
│   │   ├── Checks
│   │   ├── Alert Rules
│   │   ├── Incidents
│   │   └── Status Pages
│   ├── Backup (Cluster)
│   │   ├── Jobs
│   │   ├── Repositories
│   │   └── Snapshots
│   ├── Analytics (Cluster)
│   │   ├── Dashboards
│   │   ├── Metrics
│   │   ├── Data Sources
│   │   ├── Visualizations
│   │   └── Alerts
│   └── Automation (Cluster)
│       ├── Jobs
│       └── Tasks
│
├── Config
│   ├── Templates (Cluster)
│   │   ├── Templates
│   │   ├── Profiles
│   │   ├── Variables
│   │   └── Deployments
│   └── Secrets (Cluster)
│       ├── Secrets
│       └── Access Log
│
└── CMS
    ├── Pages
    ├── Posts
    ├── Categories
    ├── Tags
    └── Menus
```

---

## Icon Standardization

### Core Package
| Resource | Current | Proposed |
|----------|---------|----------|
| Setting | heroicon-o-cog-6-tooth | Heroicon::OutlinedCog6Tooth |
| Plugin | heroicon-o-puzzle-piece | Heroicon::OutlinedPuzzlePiece |
| AuditLog | heroicon-o-document-text | Heroicon::OutlinedClipboardDocumentList |
| SetupTemplate | OutlinedRectangleStack | Heroicon::OutlinedDocumentDuplicate |
| SetupJob | OutlinedRectangleStack | Heroicon::OutlinedPlayCircle |
| SetupComponent | OutlinedRectangleStack | Heroicon::OutlinedCube |
| MigrationJob | OutlinedRectangleStack | Heroicon::OutlinedArrowPath |

### Fleet Package
| Resource | Current | Proposed |
|----------|---------|----------|
| FleetVenue | heroicon-o-map-pin | Heroicon::OutlinedMapPin |
| FleetVsite | heroicon-o-building-office | Heroicon::OutlinedBuildingOffice |
| FleetVnode | heroicon-o-server | Heroicon::OutlinedServer |
| FleetVhost | heroicon-o-computer-desktop | Heroicon::OutlinedComputerDesktop |
| IpNetwork | OutlinedRectangleStack | Heroicon::OutlinedGlobeAlt |
| IpAddress | OutlinedRectangleStack | Heroicon::OutlinedHashtag |
| IpReservation | OutlinedRectangleStack | Heroicon::OutlinedCalendar |
| WireguardServer | OutlinedRectangleStack | Heroicon::OutlinedShieldCheck |
| WireguardPeer | OutlinedRectangleStack | Heroicon::OutlinedUserGroup |
| Database | OutlinedRectangleStack | Heroicon::OutlinedCircleStack |
| DatabaseConnection | OutlinedRectangleStack | Heroicon::OutlinedLink |
| DatabaseCredential | OutlinedRectangleStack | Heroicon::OutlinedKey |

### DNS Package
| Resource | Current | Proposed |
|----------|---------|----------|
| DnsZone | OutlinedRectangleStack | Heroicon::OutlinedGlobeAlt |
| DnsRecord | OutlinedRectangleStack | Heroicon::OutlinedListBullet |
| DnsProvider | OutlinedRectangleStack | Heroicon::OutlinedCloud |
| DomainRegistration | OutlinedRectangleStack | Heroicon::OutlinedDocumentCheck |
| DomainRegistrar | OutlinedRectangleStack | Heroicon::OutlinedBuildingOffice2 |

### Mail Package
| Resource | Current | Proposed |
|----------|---------|----------|
| MailServer | OutlinedRectangleStack | Heroicon::OutlinedServerStack |
| MailDomain | OutlinedRectangleStack | Heroicon::OutlinedAtSymbol |
| Mailbox | OutlinedInboxArrowDown | Heroicon::OutlinedInboxArrowDown |
| MailAlias | OutlinedRectangleStack | Heroicon::OutlinedArrowsRightLeft |
| MailQueue | OutlinedRectangleStack | Heroicon::OutlinedQueueList |
| MailLog | OutlinedRectangleStack | Heroicon::OutlinedDocumentText |

### Web Package
| Resource | Current | Proposed |
|----------|---------|----------|
| WebServer | OutlinedRectangleStack | Heroicon::OutlinedServer |
| VirtualHost | OutlinedRectangleStack | Heroicon::OutlinedGlobeAlt |
| WebApplication | OutlinedRectangleStack | Heroicon::OutlinedWindow |
| SslCertificate | OutlinedRectangleStack | Heroicon::OutlinedLockClosed |
| SslCertificateDeployment | OutlinedRectangleStack | Heroicon::OutlinedArrowUpTray |

### Ops Package
| Resource | Current | Proposed |
|----------|---------|----------|
| MonitoringCheck | OutlinedRectangleStack | Heroicon::OutlinedClipboardDocumentCheck |
| AlertRule | OutlinedRectangleStack | Heroicon::OutlinedBell |
| Incident | OutlinedRectangleStack | Heroicon::OutlinedExclamationTriangle |
| StatusPage | OutlinedRectangleStack | Heroicon::OutlinedSignal |
| BackupJob | OutlinedRectangleStack | Heroicon::OutlinedCloudArrowUp |
| BackupRepository | OutlinedServerStack | Heroicon::OutlinedServerStack |
| BackupSnapshot | OutlinedRectangleStack | Heroicon::OutlinedCamera |
| AnalyticsDashboard | OutlinedRectangleStack | Heroicon::OutlinedPresentationChartBar |
| AnalyticsMetric | OutlinedRectangleStack | Heroicon::OutlinedChartBar |
| AnalyticsDataSource | OutlinedRectangleStack | Heroicon::OutlinedCube |
| AnalyticsVisualization | OutlinedRectangleStack | Heroicon::OutlinedChartPie |
| AnalyticsAlert | OutlinedRectangleStack | Heroicon::OutlinedBellAlert |
| AutomationJob | OutlinedRectangleStack | Heroicon::OutlinedClock |
| AutomationTask | OutlinedRectangleStack | Heroicon::OutlinedCheckCircle |

### Config Package
| Resource | Current | Proposed |
|----------|---------|----------|
| ConfigTemplate | OutlinedRectangleStack | Heroicon::OutlinedDocumentDuplicate |
| ConfigProfile | OutlinedRectangleStack | Heroicon::OutlinedUserCircle |
| ConfigVariable | OutlinedRectangleStack | Heroicon::OutlinedVariable |
| ConfigDeployment | OutlinedRectangleStack | Heroicon::OutlinedRocketLaunch |
| Secret | OutlinedRectangleStack | Heroicon::OutlinedLockClosed |
| SecretAccess | OutlinedRectangleStack | Heroicon::OutlinedEye |

---

## Command Redistribution

### Commands to Move from CLI to Mail
```
addvmail, chvmail, delvmail, shvmail
addvalias, chvalias, delvalias, shvalias
UserPasswordCommand → mail:password
UserPasswordShowCommand → mail:password-show
UserShowCommand → mail:show-user
```

### Commands to Move from CLI to Core
```
addvconf, chvconf, delvconf, shvconf
addvhost, chvhost, delvhost, shvhost
chperms, validate
addssh, chssh, delssh, shssh (already in core)
setup, migrate commands
```

### Commands to Move from Cron to Ops
```
AutomationJob commands → ops:automation-*
AutomationTask commands → ops:task-*
```

### Commands Staying in App
```
addsw, chsw, delsw, shsw (Synergy Wholesale - app-specific)
shfleet (convenience alias)
```

---

## Implementation Phases

### Phase 1: Low-Risk Mergers (2-3 hours)
- [ ] Merge admin resources into core
- [ ] Merge cron models/services/resources into ops
- [ ] Update navigation groups
- [ ] Run tests

### Phase 2: Critical Fixes (4-6 hours)
- [ ] Move VConf from cli to core
- [ ] Move RemoteExecutionService from cli to core
- [ ] Update ALL imports across all packages
- [ ] Run tests

### Phase 3: Network Merger (6-8 hours)
- [ ] Move ipam models/services/resources into fleet
- [ ] Move wg models/services/resources into fleet
- [ ] Create Fleet clusters
- [ ] Update dependencies
- [ ] Run tests

### Phase 4: CLI Merger (8-12 hours)
- [ ] Move remaining cli services to core
- [ ] Move setup/migration resources to core
- [ ] Reorganize core with namespaces
- [ ] Update all imports
- [ ] Run tests

### Phase 5: Cross-Package Realignment (4-6 hours)
- [ ] Move MailCredential to mail
- [ ] Move Database* to fleet
- [ ] Move mail commands from cli to mail
- [ ] Create unified CredentialInterface
- [ ] Run tests

### Phase 6: Implement Clusters (8-10 hours)
- [ ] Create all cluster classes
- [ ] Update resources with $cluster property
- [ ] Set up cluster navigation
- [ ] Run tests

### Phase 7: Icon Standardization (2-3 hours)
- [ ] Update all resource icons
- [ ] Verify Heroicon enum usage
- [ ] Run visual tests

### Phase 8: Command Redistribution (4-6 hours)
- [ ] Move commands to correct packages
- [ ] Update command namespaces
- [ ] Update documentation
- [ ] Run tests

### Phase 9: Finalization (4-6 hours)
- [ ] Remove empty packages (admin, cli, cron, ipam, wg)
- [ ] Update composer.json files
- [ ] Update all documentation
- [ ] Full test suite
- [ ] Performance testing

---

## Verification Checklist

After each phase:
- [ ] All tests pass
- [ ] No circular dependencies
- [ ] Navigation works correctly
- [ ] All resources accessible
- [ ] No broken imports
- [ ] Database migrations intact

---

## Rollback Strategy

Each phase creates a git branch:
```
restructure/phase-1-admin-cron
restructure/phase-2-critical-fixes
restructure/phase-3-network-merger
...
```

If issues arise, rollback to previous branch.

---

## Risk Assessment

| Phase | Risk | Mitigation |
|-------|------|------------|
| 1 | Low | Simple moves, few dependencies |
| 2 | Medium | VConf is critical, comprehensive import updates |
| 3 | Medium | Multiple models, need careful relationship updates |
| 4 | High | Large merge, extensive testing required |
| 5 | Medium | Cross-package moves, import chain updates |
| 6 | Low | Additive changes, no breaking changes |
| 7 | Low | Cosmetic changes only |
| 8 | Medium | Command moves affect CLI users |
| 9 | Low | Cleanup, well-tested by this point |

---

## Success Metrics

1. **Package Count:** 13 → 8
2. **Navigation Groups:** 13 → 7 (with clusters)
3. **Icon Differentiation:** 100% unique icons per package
4. **Test Coverage:** Maintained or improved
5. **No Circular Dependencies:** Verified
6. **User Feedback:** Improved navigation UX
