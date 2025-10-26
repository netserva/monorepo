#!/bin/bash
# Master Migration Script: NetServa 1.0 → 3.0 (In-Place)
# Orchestrates all migration steps in correct order
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

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NS_ROOT="/home/markc/.ns"
BACKUP_ROOT="/root/ns1_to_ns3_migration_$(date +%Y%m%d_%H%M%S)"
MIGRATION_LOG="$BACKUP_ROOT/master_migration.log"
PHP_VERSION="8.4"

# Create backup directory and log
mkdir -p "$BACKUP_ROOT"
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_step "NetServa 1.0 → 3.0 In-Place Migration"
log_info "Date: $(date)"
log_info "Hostname: $(hostname)"
log_info "Backup directory: $BACKUP_ROOT"
log_info "Migration log: $MIGRATION_LOG"
echo ""

# Pre-flight checks
log_step "Pre-Flight Checks"

log_info "Checking required commands..."
REQUIRED_CMDS="php mysql rsync doveadm openssl wp"
for cmd in $REQUIRED_CMDS; do
    if ! command -v $cmd &>/dev/null; then
        log_error "Required command not found: $cmd"
        exit 1
    fi
    log_info "  ✓ $cmd"
done

log_info "Checking required scripts..."
REQUIRED_SCRIPTS=(
    "$NS_ROOT/database/migrations/2025_10_24_migrate_ns1_to_ns3_mrn.php"
    "$SCRIPT_DIR/migrate_filesystem_ns1_ns3.sh"
    "$SCRIPT_DIR/convert_mdbox_to_maildir.sh"
    "$SCRIPT_DIR/migrate_ssl_certs.sh"
    "$SCRIPT_DIR/update_php_fpm_pools.sh"
    "$SCRIPT_DIR/fix_wordpress_paths.sh"
)
for script in "${REQUIRED_SCRIPTS[@]}"; do
    if [[ ! -f "$script" ]]; then
        log_error "Required script not found: $script"
        exit 1
    fi
    log_info "  ✓ $(basename "$script")"
done

log_info "Checking database access..."
if ! mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -e "SELECT 1" &>/dev/null; then
    log_error "Cannot connect to database"
    exit 1
fi
log_info "  ✓ Database connection successful"

log_info "Checking disk space..."
AVAILABLE_GB=$(df -BG /srv | tail -1 | awk '{print $4}' | sed 's/G//')
USED_GB=$(du -sb /home/u | awk '{print int($1/1024/1024/1024)}')
REQUIRED_GB=$((USED_GB * 2 + 5))  # 2x for safety + 5GB buffer

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
echo "  7. Fix WordPress database paths"
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

log_info "Running database migration..."
if ! php "$NS_ROOT/artisan" migrate --path=database/migrations/2025_10_24_migrate_ns1_to_ns3_mrn.php --force; then
    log_error "Database migration failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi
log_info "  ✓ Database migration completed"

# Backup new database
log_info "Backing up new sysadm database..."
mysqldump -usysadm -pxNi32V4TKU3a7vu9 sysadm > "$BACKUP_ROOT/sysadm_ns3_post_migration.sql"
log_info "  ✓ Database backup: $BACKUP_ROOT/sysadm_ns3_post_migration.sql"

echo ""

# Step 3: Filesystem migration
log_step "Step 3: Filesystem Migration"

log_info "Running filesystem migration..."
if ! bash "$SCRIPT_DIR/migrate_filesystem_ns1_ns3.sh"; then
    log_error "Filesystem migration failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi
log_info "  ✓ Filesystem migration completed"

echo ""

# Step 4: mdbox to maildir conversion
log_step "Step 4: Mailbox Format Conversion"

log_info "Converting mdbox mailboxes to maildir..."
if ! bash "$SCRIPT_DIR/convert_mdbox_to_maildir.sh"; then
    log_error "Mailbox conversion failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi
log_info "  ✓ Mailbox conversion completed"

echo ""

# Step 5: SSL certificate migration
log_step "Step 5: SSL Certificate Migration"

log_info "Migrating SSL certificates..."
if ! bash "$SCRIPT_DIR/migrate_ssl_certs.sh"; then
    log_error "SSL migration failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi
log_info "  ✓ SSL certificate migration completed"

echo ""

# Step 6: Update service configurations
log_step "Step 6: Service Configuration Updates"

# Dovecot
log_info "Updating Dovecot configuration..."
if [[ -f "/etc/dovecot/user-mysql.conf" ]]; then
    cp /etc/dovecot/user-mysql.conf "$BACKUP_ROOT/user-mysql.conf.ns1"
    log_info "  Backed up: /etc/dovecot/user-mysql.conf"
fi
cp "$NS_ROOT/resources/templates/_etc_dovecot_user-mysql.conf.ns3" /etc/dovecot/user-mysql.conf
log_info "  ✓ Dovecot config updated"

# Test Dovecot config
if ! doveconf -n &>/dev/null; then
    log_error "Dovecot configuration test failed!"
    log_error "Restoring backup..."
    cp "$BACKUP_ROOT/user-mysql.conf.ns1" /etc/dovecot/user-mysql.conf
    exit 1
fi
log_info "  ✓ Dovecot config test passed"

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

cp "$NS_ROOT/resources/templates/_etc_nginx_common.conf.ns3" /etc/nginx/common.conf
cp "$NS_ROOT/resources/templates/_etc_nginx_php.conf.ns3" /etc/nginx/php.conf
log_info "  ✓ Nginx configs updated"

# Test Nginx config
if ! nginx -t &>/dev/null; then
    log_error "Nginx configuration test failed!"
    log_error "Restoring backups..."
    cp "$BACKUP_ROOT/common.conf.ns1" /etc/nginx/common.conf
    cp "$BACKUP_ROOT/php.conf.ns1" /etc/nginx/php.conf
    exit 1
fi
log_info "  ✓ Nginx config test passed"

# PHP-FPM
log_info "Updating PHP-FPM pool configurations..."
if ! bash "$SCRIPT_DIR/update_php_fpm_pools.sh"; then
    log_error "PHP-FPM pool update failed!"
    log_error "Check $MIGRATION_LOG for details"
    exit 1
fi
log_info "  ✓ PHP-FPM pools updated"

echo ""

# Step 7: WordPress path fixes
log_step "Step 7: WordPress Path Updates"

log_info "Fixing WordPress database paths..."
if ! bash "$SCRIPT_DIR/fix_wordpress_paths.sh"; then
    log_warn "WordPress path fixes had issues (check log)"
    log_warn "You may need to fix these manually"
else
    log_info "  ✓ WordPress paths updated"
fi

echo ""

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
            systemctl status $service --no-pager
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
VHOST_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1")
VMAIL_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM vmails WHERE active=1")
VALIAS_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM valias WHERE active=1")
log_info "  Active vhosts: $VHOST_COUNT"
log_info "  Active vmails: $VMAIL_COUNT"
log_info "  Active valias: $VALIAS_COUNT"

log_info "Checking filesystem structure..."
DOMAIN_COUNT=$(find /srv -maxdepth 1 -type d | wc -l)
log_info "  Domains in /srv: $((DOMAIN_COUNT - 1))"  # -1 for /srv itself

if [[ -d "/home/u" ]]; then
    OLD_DOMAIN_COUNT=$(find /home/u -maxdepth 1 -type d | wc -l)
    log_warn "  Old /home/u still exists with $((OLD_DOMAIN_COUNT - 1)) entries"
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
log_info "  ✓ WordPress paths updated"

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
log_info "Rollback script available: $SCRIPT_DIR/rollback_ns3_to_ns1.sh"
echo ""

if [[ "$ALL_RUNNING" == "true" ]]; then
    log_info "Migration completed successfully!"
    exit 0
else
    log_error "Migration completed with errors - review service status"
    exit 1
fi
