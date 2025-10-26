#!/bin/bash
# Convert mdbox mailboxes to maildir format
# For NS 1.0 → NS 3.0 migration
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
DB_USER="sysadm"
DB_PASS="xNi32V4TKU3a7vu9"
DB_NAME="sysadm"
CONVERSION_LOG="/var/log/mdbox_to_maildir.log"

# Create log
touch "$CONVERSION_LOG"
exec > >(tee -a "$CONVERSION_LOG") 2>&1

log_info "Starting mdbox → maildir conversion"
log_info "Date: $(date)"
echo "---"

# Get list of mdbox users from old database (sysadm_bkp)
log_info "Querying database for mdbox users..."
MDBOX_USERS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"_bkp -sN -e \
    "SELECT user, home FROM vmails WHERE mailbox = 'mdbox' AND active = 1")

if [[ -z "$MDBOX_USERS" ]]; then
    log_info "No mdbox users found - all mailboxes already use maildir"
    exit 0
fi

log_info "Found mdbox users:"
echo "$MDBOX_USERS" | while read user home; do
    echo "  - $user ($home)"
done
echo ""

# Convert each mdbox user
CONVERTED=0
FAILED=0

while read -r USER OLD_HOME; do
    log_info "Converting: $USER"

    # Calculate new maildir path (NS 3.0 structure)
    DOMAIN=$(echo "$USER" | cut -d'@' -f2)
    MAILBOX=$(echo "$USER" | cut -d'@' -f1)
    NEW_HOME="/srv/$DOMAIN/msg/$MAILBOX"
    MAILDIR_PATH="$NEW_HOME/Maildir"

    log_info "  Old (mdbox):   $OLD_HOME"
    log_info "  New (maildir): $MAILDIR_PATH"

    # Create maildir directory if it doesn't exist
    if [[ ! -d "$NEW_HOME" ]]; then
        log_info "  Creating $NEW_HOME"
        mkdir -p "$NEW_HOME"

        # Get UID/GID from database
        UIDGID=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"_bkp -sN -e \
            "SELECT uid, gid FROM vmails WHERE user = '$USER'")
        UID=$(echo "$UIDGID" | awk '{print $1}')
        GID=$(echo "$UIDGID" | awk '{print $2}')

        chown "$UID:$GID" "$NEW_HOME"
    fi

    # Check if old mdbox exists
    if [[ ! -d "$OLD_HOME" ]]; then
        log_warn "  Old mailbox not found at $OLD_HOME, skipping"
        continue
    fi

    # Dry run first
    log_info "  Running dry-run conversion test..."
    if doveadm -o mail_location=mdbox:"$OLD_HOME" \
               -o mail_location2=maildir:"$MAILDIR_PATH" \
               sync -u "$USER" -1 maildir:"$MAILDIR_PATH" 2>&1 | grep -q "Error"; then
        log_error "  Dry-run failed for $USER"
        ((FAILED++))
        continue
    fi

    # Actual conversion
    log_info "  Converting mdbox → maildir..."
    if doveadm sync -u "$USER" -1 \
        -o mail_location=mdbox:"$OLD_HOME" \
        maildir:"$MAILDIR_PATH"; then

        log_info "  ✓ Conversion successful"

        # Verify maildir was created
        if [[ -d "$MAILDIR_PATH" ]]; then
            MSG_COUNT=$(find "$MAILDIR_PATH" -type f | wc -l)
            log_info "    Messages found: $MSG_COUNT"

            # Set correct ownership
            UIDGID=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"_bkp -sN -e \
                "SELECT uid, gid FROM vmails WHERE user = '$USER'")
            UID=$(echo "$UIDGID" | awk '{print $1}')
            GID=$(echo "$UIDGID" | awk '{print $2}')

            chown -R "$UID:$GID" "$MAILDIR_PATH"
            chmod -R 700 "$MAILDIR_PATH"

            log_info "    Ownership set to $UID:$GID"
            ((CONVERTED++))

            # Backup old mdbox (don't delete yet)
            log_info "  Backing up old mdbox to $OLD_HOME.mdbox_backup"
            mv "$OLD_HOME" "$OLD_HOME.mdbox_backup"
        else
            log_error "  Maildir not created at $MAILDIR_PATH"
            ((FAILED++))
        fi
    else
        log_error "  Conversion failed for $USER"
        ((FAILED++))
    fi

    echo ""
done <<< "$MDBOX_USERS"

# Summary
echo "---"
log_info "Conversion Summary:"
log_info "  Converted: $CONVERTED mailboxes"
log_info "  Failed:    $FAILED mailboxes"

if [[ $FAILED -gt 0 ]]; then
    log_error "Some conversions failed! Check log: $CONVERSION_LOG"
    exit 1
fi

log_info "mdbox → maildir conversion completed successfully!"
log_info "Old mdbox files backed up with .mdbox_backup suffix"
log_info "Log file: $CONVERSION_LOG"
