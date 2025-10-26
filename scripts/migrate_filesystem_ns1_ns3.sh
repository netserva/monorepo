#!/bin/bash
# NetServa 1.0 → 3.0 Filesystem Migration Script
# Migrates /home/u/<domain> → /srv/<domain> with new directory structure
# Copyright (C) 2025 Mark Constable <markc@renta.net> (AGPL-3.0)

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Configuration
OLD_BASE="/home/u"
NEW_BASE="/srv"
MIGRATION_LOG="/var/log/ns_migration.log"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root"
    exit 1
fi

# Create log file
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_info "Starting NS 1.0 → NS 3.0 Filesystem Migration"
log_info "Date: $(date)"
log_info "Old base: $OLD_BASE"
log_info "New base: $NEW_BASE"
echo "---"

# Get list of domains
if [[ ! -d "$OLD_BASE" ]]; then
    log_error "Source directory $OLD_BASE does not exist!"
    exit 1
fi

DOMAINS=$(find "$OLD_BASE" -maxdepth 1 -mindepth 1 -type d -printf "%f\n" | sort)

if [[ -z "$DOMAINS" ]]; then
    log_warn "No domains found in $OLD_BASE"
    exit 0
fi

log_info "Found domains to migrate:"
echo "$DOMAINS" | while read domain; do
    echo "  - $domain"
done
echo ""

# Create /srv if it doesn't exist
if [[ ! -d "$NEW_BASE" ]]; then
    log_info "Creating $NEW_BASE directory"
    mkdir -p "$NEW_BASE"
fi

# Migrate each domain
MIGRATED=0
SKIPPED=0
FAILED=0

for DOMAIN in $DOMAINS; do
    log_info "Processing: $DOMAIN"

    OLD_PATH="$OLD_BASE/$DOMAIN"
    NEW_PATH="$NEW_BASE/$DOMAIN"

    # Skip if destination already exists
    if [[ -d "$NEW_PATH" ]]; then
        log_warn "  Destination $NEW_PATH already exists, skipping"
        ((SKIPPED++))
        continue
    fi

    # Get ownership of old directory
    OLD_OWNER=$(stat -c "%u:%g" "$OLD_PATH")
    log_info "  Owner: $OLD_OWNER"

    # Create new directory structure
    log_info "  Creating directory structure in $NEW_PATH"
    mkdir -p "$NEW_PATH"/{web/{app/public,run,log},msg}

    # Set ownership on new directories
    chown -R "$OLD_OWNER" "$NEW_PATH"

    # Migrate web files
    if [[ -d "$OLD_PATH/var/www/html" ]]; then
        log_info "  Migrating web files: var/www/html → web/app/public"
        # Use rsync to preserve permissions and timestamps
        rsync -a "$OLD_PATH/var/www/html/" "$NEW_PATH/web/app/public/"

        # Verify migration
        if [[ $? -eq 0 ]]; then
            log_info "  Web files migrated successfully"
        else
            log_error "  Failed to migrate web files"
            ((FAILED++))
            continue
        fi
    else
        log_warn "  No web files found in $OLD_PATH/var/www/html"
    fi

    # Migrate mailboxes (home directories)
    if [[ -d "$OLD_PATH/home" ]]; then
        log_info "  Migrating mailboxes: home/* → msg/*"
        for MAILBOX in "$OLD_PATH/home"/*; do
            if [[ -d "$MAILBOX" ]]; then
                MAILBOX_NAME=$(basename "$MAILBOX")
                log_info "    - $MAILBOX_NAME"
                rsync -a "$MAILBOX/" "$NEW_PATH/msg/$MAILBOX_NAME/"
            fi
        done
    else
        log_info "  No mailboxes found in $OLD_PATH/home"
    fi

    # Migrate logs if they exist
    if [[ -d "$OLD_PATH/var/log" ]]; then
        log_info "  Migrating logs: var/log → web/log"
        rsync -a "$OLD_PATH/var/log/" "$NEW_PATH/web/log/"
    fi

    # Create PHP-FPM run directory
    mkdir -p "$NEW_PATH/web/run"
    chown "$OLD_OWNER" "$NEW_PATH/web/run"

    # Set correct permissions
    log_info "  Setting permissions"
    chmod 755 "$NEW_PATH"
    chmod 755 "$NEW_PATH/web"
    chmod 755 "$NEW_PATH/web/app"
    chmod 755 "$NEW_PATH/web/app/public"
    chmod 755 "$NEW_PATH/web/run"
    chmod 755 "$NEW_PATH/web/log"
    chmod 755 "$NEW_PATH/msg"

    # Set www-data as group for web directories
    chgrp -R www-data "$NEW_PATH/web"

    # Verify migration
    log_info "  Verifying migration..."
    if [[ -d "$NEW_PATH/web/app/public" ]]; then
        WEB_FILES=$(find "$NEW_PATH/web/app/public" -type f | wc -l)
        log_info "    Web files: $WEB_FILES"
    fi

    if [[ -d "$NEW_PATH/msg" ]]; then
        MAILBOXES=$(find "$NEW_PATH/msg" -mindepth 1 -maxdepth 1 -type d | wc -l)
        log_info "    Mailboxes: $MAILBOXES"
    fi

    ((MIGRATED++))
    log_info "  ✓ Migration complete for $DOMAIN"
    echo ""
done

# Summary
echo "---"
log_info "Migration Summary:"
log_info "  Migrated: $MIGRATED domains"
log_info "  Skipped:  $SKIPPED domains"
log_info "  Failed:   $FAILED domains"

if [[ $FAILED -gt 0 ]]; then
    log_error "Some migrations failed! Check log: $MIGRATION_LOG"
    exit 1
fi

log_info "Filesystem migration completed successfully!"
log_info "Old files remain in $OLD_BASE for rollback"
log_info "Log file: $MIGRATION_LOG"
