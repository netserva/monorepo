#!/bin/bash
# Rollback Script: NetServa 3.0 → 1.0 (In-Place)
# Restores system to NS 1.0 state after failed migration
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
PHP_VERSION="8.4"
ROLLBACK_LOG="/var/log/ns3_rollback_$(date +%Y%m%d_%H%M%S).log"

# Create log
touch "$ROLLBACK_LOG"
exec > >(tee -a "$ROLLBACK_LOG") 2>&1

log_step "NetServa 3.0 → 1.0 Rollback"
log_info "Date: $(date)"
log_info "Hostname: $(hostname)"
log_info "Rollback log: $ROLLBACK_LOG"
echo ""

# Find most recent migration backup
log_info "Searching for migration backups..."
BACKUP_DIRS=$(find /root -maxdepth 1 -type d -name "ns1_to_ns3_migration_*" 2>/dev/null | sort -r)

if [[ -z "$BACKUP_DIRS" ]]; then
    log_error "No migration backup directories found in /root/"
    log_error "Expected: /root/ns1_to_ns3_migration_YYYYMMDD_HHMMSS/"
    exit 1
fi

# Show available backups
log_info "Available migration backups:"
echo "$BACKUP_DIRS" | nl
echo ""

# Use most recent by default
LATEST_BACKUP=$(echo "$BACKUP_DIRS" | head -1)
log_info "Most recent backup: $LATEST_BACKUP"
echo ""

read -p "Use this backup for rollback? (yes/no): " USE_LATEST
if [[ "$USE_LATEST" != "yes" ]]; then
    read -p "Enter backup directory path: " BACKUP_DIR
    if [[ ! -d "$BACKUP_DIR" ]]; then
        log_error "Directory not found: $BACKUP_DIR"
        exit 1
    fi
else
    BACKUP_DIR="$LATEST_BACKUP"
fi

log_info "Using backup: $BACKUP_DIR"
echo ""

# Verify backup contents
log_info "Verifying backup directory contents..."
REQUIRED_BACKUPS=(
    "sysadm_bkp_before_migration.sql"
    "common.conf.ns1"
    "php.conf.ns1"
    "user-mysql.conf.ns1"
)

MISSING_FILES=0
for file in "${REQUIRED_BACKUPS[@]}"; do
    if [[ ! -f "$BACKUP_DIR/$file" ]]; then
        log_error "Missing backup file: $file"
        ((MISSING_FILES++))
    else
        log_info "  ✓ $file"
    fi
done

if [[ $MISSING_FILES -gt 0 ]]; then
    log_error "Backup directory incomplete - cannot rollback safely"
    exit 1
fi

echo ""

# User confirmation
log_warn "This rollback will:"
echo "  1. Stop mail and web services"
echo "  2. Restore database (sysadm_bkp → sysadm)"
echo "  3. Restore service configurations (Dovecot, Nginx, PHP-FPM)"
echo "  4. Optionally restore /home/u from /srv"
echo "  5. Restart all services"
echo ""
log_warn "IMPORTANT: New data created after migration will be LOST!"
echo ""

read -p "Continue with rollback? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    log_error "Rollback cancelled by user"
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

# Step 2: Restore database
log_step "Step 2: Restoring Database"

log_info "Backing up current sysadm database (just in case)..."
mysqldump -usysadm -pxNi32V4TKU3a7vu9 sysadm > "/tmp/sysadm_pre_rollback_$(date +%Y%m%d_%H%M%S).sql" 2>/dev/null || true

log_info "Checking if sysadm_bkp database exists..."
if mysql -usysadm -pxNi32V4TKU3a7vu9 -e "USE sysadm_bkp" 2>/dev/null; then
    log_info "  ✓ sysadm_bkp database exists"

    log_info "Dropping current sysadm database..."
    mysql -usysadm -pxNi32V4TKU3a7vu9 -e "DROP DATABASE IF EXISTS sysadm"

    log_info "Renaming sysadm_bkp → sysadm..."
    mysql -usysadm -pxNi32V4TKU3a7vu9 -e "RENAME TABLE sysadm_bkp.vhosts TO sysadm.vhosts, sysadm_bkp.vmails TO sysadm.vmails, sysadm_bkp.valias TO sysadm.valias" 2>/dev/null || {
        # If RENAME TABLE fails, use alternative method
        log_warn "RENAME TABLE failed, using CREATE DATABASE method..."
        mysql -usysadm -pxNi32V4TKU3a7vu9 -e "CREATE DATABASE sysadm"
        mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm < "$BACKUP_DIR/sysadm_bkp_before_migration.sql"
        mysql -usysadm -pxNi32V4TKU3a7vu9 -e "DROP DATABASE sysadm_bkp"
    }
    log_info "  ✓ Database restored to NS 1.0 schema"
else
    log_warn "sysadm_bkp database not found, restoring from backup file..."
    mysql -usysadm -pxNi32V4TKU3a7vu9 -e "DROP DATABASE IF EXISTS sysadm"
    mysql -usysadm -pxNi32V4TKU3a7vu9 -e "CREATE DATABASE sysadm"
    mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm < "$BACKUP_DIR/sysadm_bkp_before_migration.sql"
    log_info "  ✓ Database restored from backup file"
fi

# Verify restoration
VHOST_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1" 2>/dev/null || echo "0")
log_info "  Restored vhosts: $VHOST_COUNT"

echo ""

# Step 3: Restore service configurations
log_step "Step 3: Restoring Service Configurations"

# Dovecot
if [[ -f "$BACKUP_DIR/user-mysql.conf.ns1" ]]; then
    log_info "Restoring Dovecot configuration..."
    cp "$BACKUP_DIR/user-mysql.conf.ns1" /etc/dovecot/user-mysql.conf
    log_info "  ✓ Dovecot config restored"

    if ! doveconf -n &>/dev/null; then
        log_error "Dovecot config test failed!"
    else
        log_info "  ✓ Dovecot config test passed"
    fi
else
    log_warn "Dovecot backup not found, skipping"
fi

# Nginx
if [[ -f "$BACKUP_DIR/common.conf.ns1" ]]; then
    log_info "Restoring Nginx common.conf..."
    cp "$BACKUP_DIR/common.conf.ns1" /etc/nginx/common.conf
    log_info "  ✓ Nginx common.conf restored"
fi

if [[ -f "$BACKUP_DIR/php.conf.ns1" ]]; then
    log_info "Restoring Nginx php.conf..."
    cp "$BACKUP_DIR/php.conf.ns1" /etc/nginx/php.conf
    log_info "  ✓ Nginx php.conf restored"
fi

if ! nginx -t &>/dev/null; then
    log_error "Nginx config test failed!"
else
    log_info "  ✓ Nginx config test passed"
fi

# PHP-FPM pools
if [[ -d "$BACKUP_DIR" ]]; then
    PHP_POOL_BACKUPS=$(find "$BACKUP_DIR" -name "*.conf" -path "*/pool.d/*" 2>/dev/null || true)
    if [[ -n "$PHP_POOL_BACKUPS" ]]; then
        log_info "Restoring PHP-FPM pool configurations..."
        POOL_DIR="/etc/php/$PHP_VERSION/fpm/pool.d"
        echo "$PHP_POOL_BACKUPS" | while read backup_file; do
            pool_name=$(basename "$backup_file")
            cp "$backup_file" "$POOL_DIR/$pool_name"
            log_info "  ✓ Restored $pool_name"
        done

        if php-fpm$PHP_VERSION -t 2>&1 | grep -q "test is successful"; then
            log_info "  ✓ PHP-FPM config test passed"
        else
            log_error "PHP-FPM config test failed!"
        fi
    fi
fi

echo ""

# Step 4: Filesystem restoration (optional)
log_step "Step 4: Filesystem Restoration (Optional)"

log_warn "Filesystem restoration will move /srv/* back to /home/u/*"
log_warn "This is ONLY needed if you want to completely revert to NS 1.0 structure"
echo ""

read -p "Restore filesystem? (yes/no): " RESTORE_FS
if [[ "$RESTORE_FS" == "yes" ]]; then
    log_info "Creating /home/u if needed..."
    mkdir -p /home/u

    log_info "Finding domains in /srv..."
    DOMAINS=$(find /srv -maxdepth 1 -type d -name "*.*" 2>/dev/null || true)

    if [[ -z "$DOMAINS" ]]; then
        log_warn "No domains found in /srv"
    else
        echo "$DOMAINS" | while read srv_path; do
            domain=$(basename "$srv_path")
            old_path="/home/u/$domain"

            log_info "Processing: $domain"

            if [[ -d "$old_path" ]]; then
                log_warn "  Old path already exists: $old_path"
                read -p "  Overwrite? (yes/no): " OVERWRITE
                if [[ "$OVERWRITE" != "yes" ]]; then
                    log_warn "  Skipping $domain"
                    continue
                fi
            fi

            mkdir -p "$old_path"

            # Restore structure: /srv/<domain>/web/app/public → /home/u/<domain>/var/www/html
            if [[ -d "$srv_path/web/app/public" ]]; then
                log_info "  Restoring web files..."
                mkdir -p "$old_path/var/www"
                rsync -avz --delete "$srv_path/web/app/public/" "$old_path/var/www/html/"
            fi

            # Restore mail: /srv/<domain>/msg/* → /home/u/<domain>/home/*
            if [[ -d "$srv_path/msg" ]]; then
                log_info "  Restoring mail files..."
                mkdir -p "$old_path/home"
                rsync -avz --delete "$srv_path/msg/" "$old_path/home/"
            fi

            # Restore logs
            if [[ -d "$srv_path/web/log" ]]; then
                log_info "  Restoring logs..."
                mkdir -p "$old_path/var/log"
                rsync -avz --delete "$srv_path/web/log/" "$old_path/var/log/"
            fi

            # Restore PHP-FPM socket directory
            if [[ -d "$srv_path/web/run" ]]; then
                mkdir -p "$old_path/var/run"
            fi

            log_info "  ✓ Restored $domain"
        done

        log_info "✓ Filesystem restoration completed"
        log_warn "Review /srv and /home/u, then manually remove /srv/* if satisfied"
    fi
else
    log_info "Skipping filesystem restoration"
    log_warn "Services will start with NS 1.0 configs but NS 3.0 filesystem"
    log_warn "You may need to manually adjust paths or complete filesystem restoration later"
fi

echo ""

# Step 5: Start services
log_step "Step 5: Starting Services"

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

# Step 6: Verification
log_step "Step 6: Post-Rollback Verification"

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
VHOST_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1" 2>/dev/null || echo "0")
VMAIL_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM vmails WHERE active=1" 2>/dev/null || echo "0")
VALIAS_COUNT=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SELECT COUNT(*) FROM valias WHERE active=1" 2>/dev/null || echo "0")
log_info "  Active vhosts: $VHOST_COUNT"
log_info "  Active vmails: $VMAIL_COUNT"
log_info "  Active valias: $VALIAS_COUNT"

# Check schema
log_info "Verifying database schema..."
HAS_AID=$(mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm -Bse "SHOW COLUMNS FROM vhosts LIKE 'aid'" 2>/dev/null || echo "")
if [[ -n "$HAS_AID" ]]; then
    log_info "  ✓ NS 1.0 schema detected (aid column exists)"
else
    log_warn "  ✗ NS 3.0 schema detected - database may not be fully restored"
fi

echo ""

# Final summary
log_step "Rollback Complete!"

if [[ "$ALL_RUNNING" == "true" ]]; then
    log_info "✓ All services running"
else
    log_error "✗ Some services failed to start - check logs!"
fi

echo ""
log_info "Rollback log: $ROLLBACK_LOG"
echo ""
log_warn "Next steps:"
echo "  1. Verify database schema is NS 1.0 (aid, hid columns present)"
echo "  2. Test mail sending/receiving"
echo "  3. Test web access to all domains"
if [[ "$RESTORE_FS" != "yes" ]]; then
    echo "  4. Consider restoring filesystem from /srv to /home/u"
    echo "     Run this script again to perform filesystem restoration"
fi
echo "  5. Monitor logs: tail -f /var/log/{mail.log,nginx/error.log}"
echo ""

if [[ "$ALL_RUNNING" == "true" ]]; then
    log_info "Rollback completed successfully!"
    exit 0
else
    log_error "Rollback completed with errors - review service status"
    exit 1
fi
