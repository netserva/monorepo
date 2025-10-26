#!/bin/bash
# Update PHP-FPM pool configurations for NS 3.0
# Updates socket paths from /home/u/<domain> to /srv/<domain>/web/run
# Copyright (C) 2025 Mark Constable <markc@renta.net> (AGPL-3.0)

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root"
    exit 1
fi

# Configuration
PHP_VERSION="8.4"
POOL_DIR="/etc/php/$PHP_VERSION/fpm/pool.d"
BACKUP_DIR="/root/backups/php-fpm-pools-$(date +%Y%m%d_%H%M)"
MIGRATION_LOG="/var/log/php_fpm_migration.log"

# Create log and backup directory
mkdir -p "$BACKUP_DIR"
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_info "Starting PHP-FPM pool configuration update"
log_info "Date: $(date)"
log_info "PHP Version: $PHP_VERSION"
log_info "Pool directory: $POOL_DIR"
echo "---"

# Check if pool directory exists
if [[ ! -d "$POOL_DIR" ]]; then
    log_error "PHP-FPM pool directory not found: $POOL_DIR"
    exit 1
fi

# Backup all pool configs
log_info "Backing up pool configurations to $BACKUP_DIR"
cp -a "$POOL_DIR"/* "$BACKUP_DIR/"
log_info "Backup complete"
echo ""

# Find all pool configuration files
POOL_FILES=$(find "$POOL_DIR" -name "*.conf" -type f | grep -v "/www.conf$" || true)

if [[ -z "$POOL_FILES" ]]; then
    log_warn "No custom pool configurations found in $POOL_DIR"
    exit 0
fi

log_info "Found pool configurations:"
echo "$POOL_FILES" | while read file; do
    echo "  - $(basename "$file")"
done
echo ""

# Update each pool configuration
UPDATED=0
SKIPPED=0
FAILED=0

for POOL_FILE in $POOL_FILES; do
    POOL_NAME=$(basename "$POOL_FILE" .conf)
    log_info "Processing: $POOL_NAME"

    # Check if file contains old paths
    if ! grep -q "/home/u/" "$POOL_FILE"; then
        log_warn "  No old paths found, skipping"
        ((SKIPPED++))
        continue
    fi

    # Update listen socket path
    # /home/u/<domain>/var/run/fpm.sock → /srv/<domain>/web/run/fpm.sock
    if sed -i 's|/home/u/\([^/]*\)/var/run/fpm.sock|/srv/\1/web/run/fpm.sock|g' "$POOL_FILE"; then
        log_info "  ✓ Updated listen socket path"
    else
        log_error "  Failed to update listen socket path"
        ((FAILED++))
        continue
    fi

    # Update chroot if present
    if grep -q "^chroot" "$POOL_FILE"; then
        if sed -i 's|^chroot = /home/u/\([^/]*\)|chroot = /srv/\1/web|g' "$POOL_FILE"; then
            log_info "  ✓ Updated chroot path"
        fi
    fi

    # Update chdir if present
    if grep -q "^chdir" "$POOL_FILE"; then
        if sed -i 's|^chdir = /home/u/\([^/]*\)/var/www/html|chdir = /srv/\1/web/app/public|g' "$POOL_FILE"; then
            log_info "  ✓ Updated chdir path"
        fi
    fi

    # Update access log if present
    if grep -q "access.log" "$POOL_FILE"; then
        if sed -i 's|/home/u/\([^/]*\)/var/log/|/srv/\1/web/log/|g' "$POOL_FILE"; then
            log_info "  ✓ Updated log paths"
        fi
    fi

    # Update slowlog if present
    if grep -q "slowlog" "$POOL_FILE"; then
        if sed -i 's|/home/u/\([^/]*\)/var/log/|/srv/\1/web/log/|g' "$POOL_FILE"; then
            log_info "  ✓ Updated slowlog path"
        fi
    fi

    ((UPDATED++))
    echo ""
done

# Test PHP-FPM configuration
log_info "Testing PHP-FPM configuration..."
if php-fpm$PHP_VERSION -t 2>&1 | grep -q "test is successful"; then
    log_info "✓ PHP-FPM configuration test passed"
else
    log_error "PHP-FPM configuration test failed!"
    log_error "Review errors above and restore from backup if needed:"
    log_error "  cp $BACKUP_DIR/* $POOL_DIR/"
    ((FAILED++))
fi

# Summary
echo "---"
log_info "Migration Summary:"
log_info "  Updated: $UPDATED pools"
log_info "  Skipped: $SKIPPED pools"
log_info "  Failed:  $FAILED pools"

if [[ $FAILED -gt 0 ]]; then
    log_error "Some updates failed! Check log: $MIGRATION_LOG"
    log_error "Restore from backup: cp $BACKUP_DIR/* $POOL_DIR/"
    exit 1
fi

log_info "PHP-FPM pool configuration update completed successfully!"
log_info "Backup location: $BACKUP_DIR"
log_info "IMPORTANT: Restart PHP-FPM after filesystem migration:"
log_info "  systemctl restart php$PHP_VERSION-fpm"
log_info "Log file: $MIGRATION_LOG"
