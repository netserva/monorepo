#!/bin/bash
# Step 3: Database Migration NS 1.0 → 3.0
# Based on /root/sysadm.dbml
# Copyright (C) 2025 Mark Constable <markc@renta.net> (AGPL-3.0)

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

DB_USER="sysadm"
DB_PASS="xNi32V4TKU3a7vu9"
DB_NAME="sysadm"
BACKUP_FILE="/root/sysadm_ns1_backup_$(date +%Y%m%d_%H%M%S).sql"

echo -e "${BLUE}=== Step 3: Database Migration NS 1.0 → 3.0 ===${NC}\n"

# 1. Backup current database
log_info "Creating database backup..."
mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"
log_info "✓ Backup saved to: $BACKUP_FILE"
echo ""

# 2. Show current data counts
log_info "Current database contents:"
VHOST_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1")
VMAIL_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM vmails WHERE active=1")
VALIAS_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM valias WHERE active=1")
echo "  vhosts: $VHOST_COUNT"
echo "  vmails: $VMAIL_COUNT"
echo "  valias: $VALIAS_COUNT"
echo ""

# 3. Create sysadm_bkp database if it doesn't exist
log_info "Preparing sysadm_bkp database..."
mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS sysadm_bkp"
mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE sysadm_bkp"
log_info "✓ sysadm_bkp database created"
echo ""

# 4. Move tables to backup database
log_info "Moving tables to sysadm_bkp..."
mysql -u"$DB_USER" -p"$DB_PASS" <<'EOF'
RENAME TABLE
    sysadm.vhosts TO sysadm_bkp.vhosts,
    sysadm.vmails TO sysadm_bkp.vmails,
    sysadm.valias TO sysadm_bkp.valias;
EOF
log_info "✓ Tables moved to sysadm_bkp"
echo ""

# 5. Create NS 3.0 schema
log_info "Creating NS 3.0 schema..."
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'SCHEMA'
-- vhosts table (NS 3.0)
CREATE TABLE vhosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vhosts_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- vmails table (NS 3.0)
CREATE TABLE vmails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(255) NOT NULL UNIQUE,
    pass VARCHAR(255) NOT NULL,
    home VARCHAR(255) NOT NULL,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vmails_user (user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- valias table (NS 3.0)
CREATE TABLE valias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(255) NOT NULL UNIQUE,
    target TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_valias_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SCHEMA
log_info "✓ NS 3.0 schema created"
echo ""

# 6. Migrate data
log_info "Migrating data from NS 1.0 to NS 3.0..."

# Migrate vhosts
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'MIGRATE_VHOSTS'
INSERT INTO vhosts (id, domain, uid, gid, active, created_at, updated_at)
SELECT id, domain, uid, gid, active, created, updated
FROM sysadm_bkp.vhosts;
MIGRATE_VHOSTS
log_info "✓ vhosts migrated"

# Migrate vmails
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'MIGRATE_VMAILS'
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
    ) AS home,
    uid,
    gid,
    active,
    created,
    updated
FROM sysadm_bkp.vmails;
MIGRATE_VMAILS
log_info "✓ vmails migrated"

# Migrate valias
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'MIGRATE_VALIAS'
INSERT INTO valias (id, source, target, active, created_at, updated_at)
SELECT id, source, target, active, created, updated
FROM sysadm_bkp.valias;
MIGRATE_VALIAS
log_info "✓ valias migrated"
echo ""

# 7. Verify migration
log_info "Verifying migration..."
NEW_VHOST_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM vhosts WHERE active=1")
NEW_VMAIL_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM vmails WHERE active=1")
NEW_VALIAS_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -Bse "SELECT COUNT(*) FROM valias WHERE active=1")

echo "  vhosts: $VHOST_COUNT → $NEW_VHOST_COUNT"
echo "  vmails: $VMAIL_COUNT → $NEW_VMAIL_COUNT"
echo "  valias: $VALIAS_COUNT → $NEW_VALIAS_COUNT"
echo ""

if [[ "$VHOST_COUNT" -eq "$NEW_VHOST_COUNT" ]] && \
   [[ "$VMAIL_COUNT" -eq "$NEW_VMAIL_COUNT" ]] && \
   [[ "$VALIAS_COUNT" -eq "$NEW_VALIAS_COUNT" ]]; then
    log_info "✓ All records migrated successfully"
else
    log_error "✗ Record count mismatch!"
    exit 1
fi

# 8. Show sample vmails with new paths
log_info "Sample vmails with new paths:"
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT user, home FROM vmails LIMIT 3"
echo ""

# 9. Final summary
echo -e "${GREEN}=== Database Migration Complete ===${NC}\n"
log_info "NS 1.0 backup: $BACKUP_FILE"
log_info "NS 1.0 tables: sysadm_bkp.{vhosts,vmails,valias}"
log_info "NS 3.0 tables: sysadm.{vhosts,vmails,valias}"
echo ""
log_warn "To rollback: mysql -usysadm -pxNi32V4TKU3a7vu9 sysadm < $BACKUP_FILE"
echo ""
