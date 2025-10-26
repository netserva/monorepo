#!/bin/bash
# Fix WordPress database paths after NS 1.0 → NS 3.0 migration
# Updates /home/u/<domain>/var/www/html → /srv/<domain>/web/app/public
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
WP_CLI="/usr/local/bin/wp"
MIGRATION_LOG="/var/log/wordpress_path_migration.log"
DB_USER="sysadm"
DB_PASS="xNi32V4TKU3a7vu9"
DB_NAME="sysadm"

# Create log
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_info "Starting WordPress path migration"
log_info "Date: $(date)"
echo "---"

# Check if wp-cli is installed
if [[ ! -x "$WP_CLI" ]]; then
    log_error "wp-cli not found at $WP_CLI"
    log_error "Install with: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"
    log_error "             chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"
    exit 1
fi

log_info "wp-cli version: $($WP_CLI --version)"
echo ""

# Get list of WordPress installations from database
# Query vhosts table to find WordPress installations
WORDPRESS_DOMAINS=$(mysql -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -Bse \
    "SELECT domain FROM vhosts WHERE active=1" 2>/dev/null || echo "")

if [[ -z "$WORDPRESS_DOMAINS" ]]; then
    log_error "No active vhosts found in database"
    exit 1
fi

log_info "Checking domains for WordPress installations:"
echo "$WORDPRESS_DOMAINS" | while read domain; do
    echo "  - $domain"
done
echo ""

# Process each domain
UPDATED=0
SKIPPED=0
FAILED=0

for DOMAIN in $WORDPRESS_DOMAINS; do
    WP_PATH="/srv/$DOMAIN/web/app/public"
    OLD_PATH="/home/u/$DOMAIN/var/www/html"

    log_info "Processing: $DOMAIN"

    # Check if directory exists
    if [[ ! -d "$WP_PATH" ]]; then
        log_warn "  Directory not found: $WP_PATH"
        ((SKIPPED++))
        continue
    fi

    # Check if it's a WordPress installation
    if [[ ! -f "$WP_PATH/wp-config.php" ]]; then
        log_warn "  Not a WordPress installation (no wp-config.php)"
        ((SKIPPED++))
        continue
    fi

    log_info "  Found WordPress installation"

    # Get WordPress user/group from directory ownership
    WP_USER=$(stat -c '%U' "$WP_PATH")
    WP_GROUP=$(stat -c '%G' "$WP_PATH")
    log_info "  Owner: $WP_USER:$WP_GROUP"

    # Backup database first
    log_info "  Creating database backup..."
    if ! sudo -u "$WP_USER" $WP_CLI --path="$WP_PATH" db export "/tmp/${DOMAIN}_pre_migration.sql" --allow-root 2>&1; then
        log_error "  Database backup failed"
        ((FAILED++))
        continue
    fi
    log_info "  ✓ Database backed up to /tmp/${DOMAIN}_pre_migration.sql"

    # Perform search-replace (dry-run first)
    log_info "  Testing search-replace (dry-run)..."
    DRY_RUN_OUTPUT=$(sudo -u "$WP_USER" $WP_CLI --path="$WP_PATH" search-replace \
        "$OLD_PATH" "$WP_PATH" \
        --dry-run \
        --all-tables \
        --allow-root 2>&1 || echo "FAILED")

    if echo "$DRY_RUN_OUTPUT" | grep -q "FAILED"; then
        log_error "  Dry-run failed: $DRY_RUN_OUTPUT"
        ((FAILED++))
        continue
    fi

    # Check if any replacements would be made
    REPLACEMENT_COUNT=$(echo "$DRY_RUN_OUTPUT" | grep -oP '\d+(?= replacements)' | head -1 || echo "0")

    if [[ "$REPLACEMENT_COUNT" -eq 0 ]]; then
        log_warn "  No path references found to replace"
        ((SKIPPED++))
        continue
    fi

    log_info "  Dry-run found $REPLACEMENT_COUNT replacements needed"

    # Perform actual search-replace
    log_info "  Performing search-replace..."
    if sudo -u "$WP_USER" $WP_CLI --path="$WP_PATH" search-replace \
        "$OLD_PATH" "$WP_PATH" \
        --all-tables \
        --allow-root 2>&1; then
        log_info "  ✓ Successfully updated $REPLACEMENT_COUNT references"
        ((UPDATED++))
    else
        log_error "  Search-replace failed"
        log_error "  Restore from backup: wp db import /tmp/${DOMAIN}_pre_migration.sql"
        ((FAILED++))
        continue
    fi

    # Flush WordPress cache
    log_info "  Flushing WordPress cache..."
    sudo -u "$WP_USER" $WP_CLI --path="$WP_PATH" cache flush --allow-root 2>&1 || true

    # Regenerate .htaccess if needed
    if [[ -f "$WP_PATH/.htaccess" ]]; then
        log_info "  Flushing rewrite rules..."
        sudo -u "$WP_USER" $WP_CLI --path="$WP_PATH" rewrite flush --allow-root 2>&1 || true
    fi

    echo ""
done

# Summary
echo "---"
log_info "Migration Summary:"
log_info "  Updated: $UPDATED WordPress sites"
log_info "  Skipped: $SKIPPED sites"
log_info "  Failed:  $FAILED sites"

if [[ $FAILED -gt 0 ]]; then
    log_error "Some updates failed! Check log: $MIGRATION_LOG"
    log_error "Database backups available in /tmp/<domain>_pre_migration.sql"
    exit 1
fi

log_info "WordPress path migration completed successfully!"
log_info "Database backups: /tmp/*_pre_migration.sql"
log_info "Log file: $MIGRATION_LOG"
