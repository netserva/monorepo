#!/bin/bash
# Master Migration Script: NetServa 1.0 → 3.0 (In-Place)
# Orchestrates all migration steps in correct order
# LOCAL VERSION - runs from /root/migration on target server
# Copyright (C) 2025 Mark Constable <markc@renta.net> (AGPL-3.0)

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "\n${BLUE}${BOLD}=== $1 ===${NC}\n"; }

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root"
    exit 1
fi

# Configuration - use local paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MIGRATION_DIR="${MIGRATION_DIR:-$SCRIPT_DIR}"
BACKUP_ROOT="/root/ns1_to_ns3_migration_$(date +%Y%m%d_%H%M%S)"
MIGRATION_LOG="$BACKUP_ROOT/master_migration.log"
PHP_VERSION="8.4"

# Database credentials
DB_USER="sysadm"
DB_PASS="xNi32V4TKU3a7vu9"
DB_NAME="sysadm"

# Create backup directory and log
mkdir -p "$BACKUP_ROOT"
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_step "NetServa 1.0 → 3.0 In-Place Migration"
log_info "Date: $(date)"
log_info "Hostname: $(hostname)"
log_info "Migration directory: $MIGRATION_DIR"
log_info "Backup directory: $BACKUP_ROOT"
log_info "Migration log: $MIGRATION_LOG"
echo ""

# Pre-flight checks
log_step "Pre-Flight Checks"

log_info "Checking required commands..."
REQUIRED_CMDS="php mysql rsync doveadm openssl"
for cmd in $REQUIRED_CMDS; do
    if ! command -v $cmd &>/dev/null; then
        log_error "Required command not found: $cmd"
        exit 1
    fi
    log_info "  ✓ $cmd"
done

# wp-cli is optional
if command -v wp &>/dev/null; then
    log_info "  ✓ wp (optional)"
    HAS_WP_CLI=true
else
    log_warn "  ✗ wp-cli not found (WordPress path fixes will be skipped)"
    HAS_WP_CLI=false
fi

log_info "Checking required scripts..."
REQUIRED_SCRIPTS=(
    "$MIGRATION_DIR/migrate_filesystem_ns1_ns3.sh"
    "$MIGRATION_DIR/convert_mdbox_to_maildir.sh"
    "$MIGRATION_DIR/migrate_ssl_certs.sh"
    "$MIGRATION_DIR/update_php_fpm_pools.sh"
)
for script in "${REQUIRED_SCRIPTS[@]}"; do
    if [[ ! -f "$script" ]]; then
        log_error "Required script not found: $script"
        exit 1
    fi
    log_info "  ✓ $(basename "$script")"
done

# Check for config templates
log_info "Checking configuration templates..."
REQUIRED_TEMPLATES=(
    "$MIGRATION_DIR/_etc_dovecot_user-mysql.conf.ns3"
    "$MIGRATION_DIR/_etc_nginx_common.conf.ns3"
    "$MIGRATION_DIR/_etc_nginx_php.conf.ns3"
)
for template in "${REQUIRED_TEMPLATES[@]}"; do
    if [[ ! -f "$template" ]]; then
        log_error "Required template not found: $template"
        exit 1
    fi
    log_info "  ✓ $(basename "$template")"
done

log_info "Checking database access..."
if ! mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" &>/dev/null; then
    log_error "Cannot connect to database"
    exit 1
fi
log_info "  ✓ Database connection successful"

log_info "Checking disk space..."
AVAILABLE_GB=$(df -BG /srv 2>/dev/null | tail -1 | awk '{print $4}' | sed 's/G//' || echo "999")
if [[ -d "/home/u" ]]; then
    USED_GB=$(du -sb /home/u 2>/dev/null | awk '{print int($1/1024/1024/1024)}' || echo "0")
    REQUIRED_GB=$((USED_GB * 2 + 5))
else
    USED_GB=0
    REQUIRED_GB=5
    log_warn "  /home/u not found - may already be migrated?"
fi

if [[ $AVAILABLE_GB -lt $REQUIRED_GB ]]; then
    log_error "Insufficient disk space"
    log_error "  Available: ${AVAILABLE_GB}GB"
    log_error "  Required:  ${REQUIRED_GB}GB (estimated)"
    exit 1
fi
log_info "  ✓ Sufficient disk space (${AVAILABLE_GB}GB available, ~${REQUIRED_GB}GB needed)"

echo ""

# User confirmation
log_warn "This migration will:"
echo "  1. Stop mail and web services"
echo "  2. Backup and migrate database (sysadm → sysadm_bkp, create new sysadm)"
echo "  3. Move /home/u/* → /srv/* with new directory structure"
echo "  4. Convert mdbox mailboxes to maildir"
echo "  5. Copy SSL certificates to /etc/ssl/<domain>/"
echo "  6. Update Dovecot, Postfix, Nginx, PHP-FPM configs"
if [[ "$HAS_WP_CLI" == "true" ]]; then
    echo "  7. Fix WordPress database paths"
fi
echo "  8. Restart all services"
echo ""
log_warn "Backup location: $BACKUP_ROOT"
echo ""

read -p "Continue with migration? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    log_error "Migration cancelled by user"
    exit 1
fi

echo ""

# Step 1: Stop services
log_step "Step 1: Stopping Services"

SERVICES="postfix dovecot nginx php${PHP_VERSION}-fpm"
for service in $SERVICES; do
    if systemctl is-active $service &>/dev/null; then
        log_info "Stopping $service..."
        systemctl stop $service
        log_info "  ✓ $service stopped"
    else
        log_warn "  $service not running"
    fi
done

echo ""

# Step 2: Database migration
log_step "Step 2: Database Migration"

log_info "Backing up current sysadm database..."
mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_ROOT/sysadm_ns1_before_migration.sql"
log_info "  ✓ Database backup: $BACKUP_ROOT/sysadm_ns1_before_migration.sql"

log_info "Performing database schema migration..."

# Check if sysadm_bkp already exists
if mysql -u"$DB_USER" -p"$DB_PASS" -e "USE sysadm_bkp" 2>/dev/null; then
    log_warn "sysadm_bkp already exists - dropping it first"
    mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE sysadm_bkp"
fi

# Rename current database to backup
log_info "Renaming sysadm → sysadm_bkp..."
mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE sysadm_bkp"
mysql -u"$DB_USER" -p"$DB_PASS" sysadm_bkp < "$BACKUP_ROOT/sysadm_ns1_before_migration.sql"
mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE sysadm"
log_info "  ✓ Old database backed up as sysadm_bkp"

# Create new NS 3.0 database
log_info "Creating new NS 3.0 database schema..."
mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE sysadm"

# Create NS 3.0 schema
mysql -u"$DB_USER" -p"$DB_PASS" sysadm <<'SCHEMA'
CREATE TABLE IF NOT EXISTS vhosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vmails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(255) NOT NULL UNIQUE,
    pass VARCHAR(255) NOT NULL,
    home VARCHAR(255) NOT NULL,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS valias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(255) NOT NULL,
    target TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY source_idx (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SCHEMA

log_info "  ✓ NS 3.0 schema created"

# Migrate data
log_info "Migrating data from NS 1.0 to NS 3.0 schema..."

# Migrate vhosts (remove aid, uname, aliases, mailboxes, quotas)
mysql -u"$DB_USER" -p"$DB_PASS" sysadm <<'MIGRATE_VHOSTS'
INSERT INTO vhosts (id, domain, uid, gid, active, created_at, updated_at)
SELECT id, domain, uid, gid, active, created, updated
FROM sysadm_bkp.vhosts;
MIGRATE_VHOSTS

# Migrate vmails (rename password→pass, update home paths, remove aid, hid, spamf, mailbox)
mysql -u"$DB_USER" -p"$DB_PASS" sysadm <<'MIGRATE_VMAILS'
INSERT INTO vmails (id, user, pass, home, uid, gid, active, created_at, updated_at)
SELECT
    id,
    user,
    password,
    CONCAT(
        '/srv/',
        SUBSTRING_INDEX(user, '@', -1),
        '/msg/',
        SUBSTRING_INDEX(user, '@', 1)
    ),
    uid,
    gid,
    active,
    created,
    updated
FROM sysadm_bkp.vmails;
MIGRATE_VMAILS

# Migrate valias (remove aid, hid)
mysql -u"$DB_USER" -p"$DB_PASS" sysadm <<'MIGRATE_VALIAS'
INSERT INTO valias (id, source, target, active, created_at, updated_at)
SELECT id, source, target, active, created, updated
FROM sysadm_bkp.valias;
MIGRATE_VALIAS

log_info "  ✓ Data migration completed"

# Verify migration
VHOST_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1")
VMAIL_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM vmails WHERE active=1")
VALIAS_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM valias WHERE active=1")
log_info "  Migrated: $VHOST_COUNT vhosts, $VMAIL_COUNT vmails, $VALIAS_COUNT valias"

# Backup new database
mysqldump -u"$DB_USER" -p"$DB_PASS" sysadm > "$BACKUP_ROOT/sysadm_ns3_post_migration.sql"
log_info "  ✓ NS 3.0 database backup: $BACKUP_ROOT/sysadm_ns3_post_migration.sql"

echo ""

# Step 3: Filesystem migration
log_step "Step 3: Filesystem Migration"

log_info "Running filesystem migration..."
if bash "$MIGRATION_DIR/migrate_filesystem_ns1_ns3.sh"; then
    log_info "  ✓ Filesystem migration completed"
else
    log_error "Filesystem migration failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi

echo ""

# Step 4: mdbox to maildir conversion
log_step "Step 4: Mailbox Format Conversion"

log_info "Converting mdbox mailboxes to maildir..."
if bash "$MIGRATION_DIR/convert_mdbox_to_maildir.sh"; then
    log_info "  ✓ Mailbox conversion completed"
else
    log_warn "Mailbox conversion had issues (check log)"
    log_warn "Continuing with migration..."
fi

echo ""

# Step 5: SSL certificate migration
log_step "Step 5: SSL Certificate Migration"

log_info "Migrating SSL certificates..."
if bash "$MIGRATION_DIR/migrate_ssl_certs.sh"; then
    log_info "  ✓ SSL certificate migration completed"
else
    log_warn "SSL migration had issues (check log)"
    log_warn "Continuing with migration..."
fi

echo ""

# Step 6: Update service configurations
log_step "Step 6: Service Configuration Updates"

# Dovecot
log_info "Updating Dovecot configuration..."
if [[ -f "/etc/dovecot/user-mysql.conf" ]]; then
    cp /etc/dovecot/user-mysql.conf "$BACKUP_ROOT/user-mysql.conf.ns1"
    log_info "  Backed up: /etc/dovecot/user-mysql.conf"
fi
cp "$MIGRATION_DIR/_etc_dovecot_user-mysql.conf.ns3" /etc/dovecot/user-mysql.conf
log_info "  ✓ Dovecot config updated"

# Test Dovecot config
if doveconf -n &>/dev/null; then
    log_info "  ✓ Dovecot config test passed"
else
    log_error "Dovecot configuration test failed!"
    log_error "Restoring backup..."
    cp "$BACKUP_ROOT/user-mysql.conf.ns1" /etc/dovecot/user-mysql.conf
    exit 1
fi

# Nginx
log_info "Updating Nginx configurations..."
if [[ -f "/etc/nginx/common.conf" ]]; then
    cp /etc/nginx/common.conf "$BACKUP_ROOT/common.conf.ns1"
    log_info "  Backed up: /etc/nginx/common.conf"
fi
if [[ -f "/etc/nginx/php.conf" ]]; then
    cp /etc/nginx/php.conf "$BACKUP_ROOT/php.conf.ns1"
    log_info "  Backed up: /etc/nginx/php.conf"
fi

cp "$MIGRATION_DIR/_etc_nginx_common.conf.ns3" /etc/nginx/common.conf
cp "$MIGRATION_DIR/_etc_nginx_php.conf.ns3" /etc/nginx/php.conf
log_info "  ✓ Nginx configs updated"

# Test Nginx config
if nginx -t &>/dev/null; then
    log_info "  ✓ Nginx config test passed"
else
    log_error "Nginx configuration test failed!"
    log_error "Restoring backups..."
    [[ -f "$BACKUP_ROOT/common.conf.ns1" ]] && cp "$BACKUP_ROOT/common.conf.ns1" /etc/nginx/common.conf
    [[ -f "$BACKUP_ROOT/php.conf.ns1" ]] && cp "$BACKUP_ROOT/php.conf.ns1" /etc/nginx/php.conf
    exit 1
fi

# PHP-FPM
log_info "Updating PHP-FPM pool configurations..."
if bash "$MIGRATION_DIR/update_php_fpm_pools.sh"; then
    log_info "  ✓ PHP-FPM pools updated"
else
    log_error "PHP-FPM pool update failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi

echo ""

# Step 7: WordPress path fixes
if [[ "$HAS_WP_CLI" == "true" ]] && [[ -f "$MIGRATION_DIR/fix_wordpress_paths.sh" ]]; then
    log_step "Step 7: WordPress Path Updates"

    log_info "Fixing WordPress database paths..."
    if bash "$MIGRATION_DIR/fix_wordpress_paths.sh"; then
        log_info "  ✓ WordPress paths updated"
    else
        log_warn "WordPress path fixes had issues (check log)"
        log_warn "You may need to fix these manually"
    fi
    echo ""
fi

# Step 8: Start services
log_step "Step 8: Starting Services"

for service in $SERVICES; do
    log_info "Starting $service..."
    if systemctl start $service; then
        sleep 2
        if systemctl is-active $service &>/dev/null; then
            log_info "  ✓ $service started successfully"
        else
            log_error "  $service failed to start!"
            systemctl status $service --no-pager || true
        fi
    else
        log_error "  Failed to start $service"
    fi
done

echo ""

# Step 9: Verification
log_step "Step 9: Post-Migration Verification"

log_info "Checking service status..."
ALL_RUNNING=true
for service in $SERVICES; do
    if systemctl is-active $service &>/dev/null; then
        log_info "  ✓ $service is running"
    else
        log_error "  ✗ $service is NOT running"
        ALL_RUNNING=false
    fi
done

log_info "Checking database..."
VHOST_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1")
VMAIL_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM vmails WHERE active=1")
VALIAS_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" sysadm -Bse "SELECT COUNT(*) FROM valias WHERE active=1")
log_info "  Active vhosts: $VHOST_COUNT"
log_info "  Active vmails: $VMAIL_COUNT"
log_info "  Active valias: $VALIAS_COUNT"

log_info "Checking filesystem structure..."
DOMAIN_COUNT=$(find /srv -maxdepth 1 -type d -name "*.*" 2>/dev/null | wc -l)
log_info "  Domains in /srv: $DOMAIN_COUNT"

if [[ -d "/home/u" ]]; then
    OLD_DOMAIN_COUNT=$(find /home/u -maxdepth 1 -type d -name "*.*" 2>/dev/null | wc -l)
    log_warn "  Old /home/u still exists with $OLD_DOMAIN_COUNT entries"
    log_warn "  Review and remove manually after verification: rm -rf /home/u"
fi

echo ""

# Final summary
log_step "Migration Complete!"

log_info "Summary:"
log_info "  ✓ Database migrated from NS 1.0 to NS 3.0 schema"
log_info "  ✓ Filesystem migrated: /home/u/* → /srv/*"
log_info "  ✓ Mailboxes converted: mdbox → maildir"
log_info "  ✓ SSL certificates copied to /etc/ssl/<domain>/"
log_info "  ✓ Service configs updated (Dovecot, Nginx, PHP-FPM)"
if [[ "$HAS_WP_CLI" == "true" ]]; then
    log_info "  ✓ WordPress paths updated"
fi

if [[ "$ALL_RUNNING" == "true" ]]; then
    log_info "  ✓ All services running"
else
    log_error "  ✗ Some services failed to start - check logs!"
fi

echo ""
log_info "Backup location: $BACKUP_ROOT"
log_info "Migration log:   $MIGRATION_LOG"
echo ""
log_warn "Next steps:"
echo "  1. Test mail sending/receiving"
echo "  2. Test web access to all domains"
echo "  3. Verify WordPress sites"
echo "  4. Check SSL certificates"
echo "  5. Monitor logs: tail -f /var/log/{mail.log,nginx/error.log}"
echo "  6. After verification, remove old data: rm -rf /home/u"
echo ""

if [[ -f "$MIGRATION_DIR/rollback_ns3_to_ns1.sh" ]]; then
    log_info "Rollback script available: $MIGRATION_DIR/rollback_ns3_to_ns1.sh"
fi
echo ""

if [[ "$ALL_RUNNING" == "true" ]]; then
    log_info "Migration completed successfully!"
    exit 0
else
    log_error "Migration completed with errors - review service status"
    exit 1
fi
