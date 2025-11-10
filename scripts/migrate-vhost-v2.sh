#!/usr/bin/env bash
# NetServa 1.0 → 3.0 VHost Migration Script (Simplified)
# Created: 2025-11-07
# Purpose: Migrate data from NS 1.0 (sca) to NS 3.0 (mrn) using php artisan addvhost
# Usage: ./migrate-vhost-v2.sh <domain>

set -euo pipefail

# Configuration
SRC_HOST="sca"
DST_HOST="mrn"
SRC_BASE="/home/u"
DST_BASE="/srv"
LOG_FILE="migrate-vhost-$(date +%Y%m%d-%H%M%S).log"
DRY_RUN="${DRY_RUN:-0}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${timestamp} [${level}] ${message}" | tee -a "$LOG_FILE"
}

info() { log "INFO" "${BLUE}$*${NC}"; }
success() { log "SUCCESS" "${GREEN}✓ $*${NC}"; }
warning() { log "WARNING" "${YELLOW}⚠ $*${NC}"; }
error() { log "ERROR" "${RED}✗ $*${NC}"; exit 1; }

# Banner
banner() {
    echo "=================================================="
    echo "  NetServa 1.0 → 3.0 VHost Migration (v2)"
    echo "  Source: $SRC_HOST (NS 1.0)"
    echo "  Destination: $DST_HOST (NS 3.0)"
    echo "  Uses: php artisan addvhost for structure"
    echo "=================================================="
}

# Usage
usage() {
    cat <<EOF
Usage: $0 <domain> [options]

Options:
    -h, --help          Show this help message
    -n, --dry-run       Show what would be done without executing
    --skip-web          Skip web files migration
    --skip-db           Skip database migration
    --skip-ssl          Skip SSL certificate migration
    --skip-create       Skip addvhost (assume structure exists)

Examples:
    $0 oziwide.com
    $0 oziwide.com --dry-run
    DRY_RUN=1 $0 oziwide.com

EOF
    exit 1
}

# Parse arguments
SKIP_WEB=0
SKIP_DB=0
SKIP_SSL=0
SKIP_CREATE=0
DOMAIN=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help) usage ;;
        -n|--dry-run) DRY_RUN=1 ;;
        --skip-web) SKIP_WEB=1 ;;
        --skip-db) SKIP_DB=1 ;;
        --skip-ssl) SKIP_SSL=1 ;;
        --skip-create) SKIP_CREATE=1 ;;
        -*) error "Unknown option: $1" ;;
        *) DOMAIN="$1" ;;
    esac
    shift
done

[[ -z "$DOMAIN" ]] && usage

# Variables
SRC_PATH="$SRC_BASE/$DOMAIN"
DST_PATH="$DST_BASE/$DOMAIN"
VHOST_CONFIG="/root/.vhosts/$DOMAIN"

# Dry-run wrapper
run_cmd() {
    local cmd="$*"
    if [[ $DRY_RUN -eq 1 ]]; then
        info "[DRY-RUN] Would execute: $cmd"
        return 0
    else
        info "Executing: $cmd"
        eval "$cmd"
    fi
}

# Pre-flight checks
preflight_checks() {
    info "Running pre-flight checks..."

    # Check required commands
    for cmd in ssh scp rsync mysql mysqldump php; do
        if ! command -v $cmd &> /dev/null; then
            error "Required command not found: $cmd"
        fi
    done
    success "All required commands available"

    # Check SSH connectivity to source
    if ! ssh -o ConnectTimeout=5 "$SRC_HOST" "exit" 2>/dev/null; then
        error "Cannot connect to source host: $SRC_HOST"
    fi
    success "Connected to source host: $SRC_HOST"

    # Check SSH connectivity to destination
    if ! ssh -o ConnectTimeout=5 "$DST_HOST" "exit" 2>/dev/null; then
        error "Cannot connect to destination host: $DST_HOST"
    fi
    success "Connected to destination host: $DST_HOST"

    # Check if domain exists on source
    if ! ssh "$SRC_HOST" "test -d $SRC_PATH"; then
        error "Domain directory not found on $SRC_HOST: $SRC_PATH"
    fi
    success "Domain exists on source: $SRC_PATH"

    # Check if vhost config exists on source
    if ! ssh "$SRC_HOST" "test -f $VHOST_CONFIG"; then
        error "VHost config not found on $SRC_HOST: $VHOST_CONFIG"
    fi
    success "VHost config exists on source"

    # Check if Laravel is available
    if ! php artisan --version &>/dev/null; then
        error "Laravel artisan not available. Run from ~/.ns/ directory"
    fi
    success "Laravel environment ready"

    success "All pre-flight checks passed"
}

# Read configuration from source
read_config() {
    info "Reading configuration from $SRC_HOST:$VHOST_CONFIG..."

    # Download config file
    scp -q "$SRC_HOST:$VHOST_CONFIG" "/tmp/${DOMAIN}.vhost" || error "Failed to download config"

    # Source the config file
    source "/tmp/${DOMAIN}.vhost"

    # Extract key variables
    VHOST="${VHOST:-$DOMAIN}"
    U_UID="${U_UID:-}"
    U_GID="${U_GID:-}"
    UUSER="${UUSER:-}"
    UPASS="${UPASS:-}"
    DNAME="${DNAME:-}"
    DPASS="${DPASS:-}"
    DUSER="${DUSER:-}"

    # Validate required variables
    [[ -z "$U_UID" ]] && error "U_UID not found in config"
    [[ -z "$U_GID" ]] && error "U_GID not found in config"
    [[ -z "$UUSER" ]] && error "UUSER not found in config"

    info "Configuration loaded:"
    info "  Domain: $VHOST"
    info "  NS 1.0 User: $UUSER (UID:$U_UID, GID:$U_GID)"
    [[ -n "$DNAME" ]] && info "  Database: $DNAME"

    success "Configuration loaded successfully"
}

# Check what exists on source
detect_components() {
    info "Detecting components on source..."

    HAS_WEB=0
    HAS_DB=0

    # Check for web files
    if ssh "$SRC_HOST" "test -d $SRC_PATH/var/www/html && find $SRC_PATH/var/www/html -mindepth 1 -maxdepth 1 | grep -q ."; then
        HAS_WEB=1
        WEB_SIZE=$(ssh "$SRC_HOST" "du -sh $SRC_PATH/var/www/html | cut -f1")
        info "  ✓ Web files detected ($WEB_SIZE)"
    else
        warning "  ✗ No web files found"
    fi

    # Check for database
    if [[ -n "$DNAME" ]] && ssh "$SRC_HOST" "mysql -e 'USE $DNAME' 2>/dev/null"; then
        HAS_DB=1
        DB_SIZE=$(ssh "$SRC_HOST" "mysql -e 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS \"Size (MB)\" FROM information_schema.TABLES WHERE table_schema = \"$DNAME\"' -sN")
        info "  ✓ Database detected: $DNAME (${DB_SIZE}MB)"
    else
        warning "  ✗ No database found"
    fi

    success "Component detection complete"
}

# Create NS 3.0 vhost structure using addvhost
create_vhost_structure() {
    if [[ $SKIP_CREATE -eq 1 ]]; then
        info "Skipping vhost creation (--skip-create)"
        return 0
    fi

    info "Creating NS 3.0 vhost structure using addvhost..."

    # Check if vhost already exists
    if ssh "$DST_HOST" "test -d $DST_PATH"; then
        warning "Vhost directory already exists on $DST_HOST: $DST_PATH"
        if [[ $DRY_RUN -eq 0 ]]; then
            read -p "Continue anyway? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                error "Migration cancelled by user"
            fi
        fi
        return 0
    fi

    # Execute addvhost command
    run_cmd "php artisan addvhost $DST_HOST $DOMAIN --skip-dns --no-interaction"

    if [[ $DRY_RUN -eq 1 ]]; then
        info "In dry-run mode, would retrieve new UID from created vhost"
        return 0
    fi

    # Get the new UID assigned by addvhost
    NEW_UID=$(ssh "$DST_HOST" "getent passwd | grep ':$DOMAIN:' | cut -d: -f3")
    NEW_USER=$(ssh "$DST_HOST" "getent passwd | grep ':$DOMAIN:' | cut -d: -f1")

    if [[ -z "$NEW_UID" ]]; then
        error "Failed to create vhost or retrieve UID"
    fi

    success "Vhost structure created: $NEW_USER (UID:$NEW_UID)"
    info "NS 1.0 had: $UUSER (UID:$U_UID) → NS 3.0 has: $NEW_USER (UID:$NEW_UID)"
}

# Migrate web files
migrate_web_files() {
    if [[ $SKIP_WEB -eq 1 ]]; then
        info "Skipping web files migration (--skip-web)"
        return 0
    fi

    if [[ $HAS_WEB -eq 0 ]]; then
        warning "No web files to migrate"
        return 0
    fi

    info "Migrating web files..."

    # Rsync from NS 1.0 var/www/html → NS 3.0 web/app/public
    # Run rsync FROM destination host (mrn can SSH to sca directly)
    if ssh "$SRC_HOST" "test -d $SRC_PATH/var/www/html"; then
        run_cmd "ssh $DST_HOST 'rsync -avz --progress $SRC_HOST:$SRC_PATH/var/www/html/ $DST_PATH/web/app/public/'"
        success "Web files migrated"
    else
        warning "No var/www/html directory found on source"
    fi
}

# Migrate database
migrate_database() {
    if [[ $SKIP_DB -eq 1 ]]; then
        info "Skipping database migration (--skip-db)"
        return 0
    fi

    if [[ $HAS_DB -eq 0 ]]; then
        warning "No database to migrate"
        return 0
    fi

    info "Migrating database: $DNAME..."

    local DUMP_FILE="/tmp/${DOMAIN}_${DNAME}.sql"
    local NEW_DBPASS=$(openssl rand -base64 16 | tr -d '/+=' | cut -c1-16)

    # Dump database on source
    info "Dumping database on $SRC_HOST..."
    run_cmd "ssh $SRC_HOST 'mysqldump $DNAME' > $DUMP_FILE"

    if [[ $DRY_RUN -eq 1 ]]; then
        info "[DRY-RUN] Would create database $DNAME on $DST_HOST"
        info "[DRY-RUN] Would import SQL dump to destination"
        info "[DRY-RUN] Would generate new database password: $NEW_DBPASS"
        success "Database migration planned"
        return 0
    fi

    if [[ -f "$DUMP_FILE" ]]; then
        local DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
        success "Database dumped ($DUMP_SIZE)"

        # Get NS 3.0 user from destination
        NEW_USER=$(ssh "$DST_HOST" "getent passwd | grep ':$DOMAIN:' | cut -d: -f1")

        # Create database and user on destination
        info "Creating database and user on $DST_HOST..."
        run_cmd "ssh $DST_HOST \"mysql -e \\\"CREATE DATABASE IF NOT EXISTS $DNAME;\\\"\""
        run_cmd "ssh $DST_HOST \"mysql -e \\\"CREATE USER IF NOT EXISTS '$NEW_USER'@'localhost' IDENTIFIED BY '$NEW_DBPASS';\\\"\""
        run_cmd "ssh $DST_HOST \"mysql -e \\\"GRANT ALL PRIVILEGES ON $DNAME.* TO '$NEW_USER'@'localhost';\\\"\""
        run_cmd "ssh $DST_HOST \"mysql -e \\\"FLUSH PRIVILEGES;\\\"\""

        # Import database
        info "Importing database to $DST_HOST..."
        run_cmd "cat $DUMP_FILE | ssh $DST_HOST 'mysql $DNAME'"

        # Copy dump to destination for backup
        run_cmd "scp $DUMP_FILE $DST_HOST:$DST_PATH/web/app/"

        # Clean up local dump
        rm -f "$DUMP_FILE"

        success "Database migrated successfully"
        info "Database credentials:"
        info "  Database: $DNAME"
        info "  User: $NEW_USER"
        info "  Password: $NEW_DBPASS"
        warning "SAVE THESE CREDENTIALS - Update wp-config.php or .env if needed"
    else
        error "Failed to dump database"
    fi
}

# Migrate SSL certificates
migrate_ssl_certs() {
    if [[ $SKIP_SSL -eq 1 ]]; then
        info "Skipping SSL certificates (--skip-ssl)"
        return 0
    fi

    info "Migrating SSL certificates..."

    if ssh "$SRC_HOST" "test -d /etc/ssl/$DOMAIN"; then
        # Create SSL directory on destination
        run_cmd "ssh $DST_HOST 'mkdir -p /etc/ssl/$DOMAIN'"

        # Copy certificates (follow symlinks to get actual files)
        run_cmd "scp -L $SRC_HOST:/etc/ssl/$DOMAIN/cert.pem $DST_HOST:/etc/ssl/$DOMAIN/"
        run_cmd "scp -L $SRC_HOST:/etc/ssl/$DOMAIN/privkey.pem $DST_HOST:/etc/ssl/$DOMAIN/"
        run_cmd "scp -L $SRC_HOST:/etc/ssl/$DOMAIN/fullchain.pem $DST_HOST:/etc/ssl/$DOMAIN/"

        success "SSL certificates migrated"
    else
        warning "No SSL certificates found for $DOMAIN on source"
        warning "You will need to generate new certificates with certbot/acme.sh"
    fi
}

# Update vconfs table with NS 1.0 credentials
update_vconfs_credentials() {
    info "Updating vconfs table with original NS 1.0 credentials..."

    # Update UPASS (user password) if it exists
    if [[ -n "$UPASS" ]]; then
        run_cmd "php artisan chvconf $DST_HOST $DOMAIN UPASS '$UPASS'"
        info "  ✓ UPASS updated"
    fi

    # Update DPASS (database password) if it exists
    if [[ -n "$DPASS" ]]; then
        run_cmd "php artisan chvconf $DST_HOST $DOMAIN DPASS '$DPASS'"
        info "  ✓ DPASS updated"
    fi

    # Store old UID/GID for reference
    if [[ -n "$U_UID" ]]; then
        run_cmd "php artisan chvconf $DST_HOST $DOMAIN OLD_U_UID '$U_UID'"
        run_cmd "php artisan chvconf $DST_HOST $DOMAIN OLD_U_GID '$U_GID'"
        run_cmd "php artisan chvconf $DST_HOST $DOMAIN OLD_UUSER '$UUSER'"
        info "  ✓ Old UID/GID/USER stored for reference"
    fi

    success "VConfs updated successfully"
}

# Fix permissions after migration
fix_permissions() {
    info "Fixing permissions on $DST_HOST:$DST_PATH..."

    if [[ $DRY_RUN -eq 1 ]]; then
        info "[DRY-RUN] Would fix ownership and permissions for web directory"
        return 0
    fi

    # Get NS 3.0 user
    NEW_USER=$(ssh "$DST_HOST" "getent passwd | grep ':$DOMAIN:' | cut -d: -f1")
    NEW_UID=$(ssh "$DST_HOST" "getent passwd $NEW_USER | cut -d: -f3")

    # Fix web directory ownership and permissions
    run_cmd "ssh $DST_HOST 'chown -R $NEW_UID:www-data $DST_PATH/web'"
    run_cmd "ssh $DST_HOST 'find $DST_PATH/web -type d -exec chmod 02750 {} \\;'"
    run_cmd "ssh $DST_HOST 'find $DST_PATH/web -type f -exec chmod 0640 {} \\;'"

    success "Permissions fixed"
}

# Reload services
reload_services() {
    info "Reloading services on $DST_HOST..."

    run_cmd "ssh $DST_HOST 'systemctl reload nginx'"
    success "Nginx reloaded"

    run_cmd "ssh $DST_HOST 'systemctl reload php8.4-fpm'"
    success "PHP-FPM reloaded"
}

# Validation
validate_migration() {
    info "Validating migration..."

    if [[ $DRY_RUN -eq 1 ]]; then
        info "[DRY-RUN] Would validate migration was successful"
        return 0
    fi

    local ERRORS=0

    # Check directory exists
    if ssh "$DST_HOST" "test -d $DST_PATH"; then
        success "Directory exists: $DST_PATH"
    else
        error "Directory missing: $DST_PATH"
        ((ERRORS++))
    fi

    # Check web files
    if [[ $HAS_WEB -eq 1 ]] && [[ $SKIP_WEB -eq 0 ]]; then
        if ssh "$DST_HOST" "test -d $DST_PATH/web/app/public"; then
            success "Web directory exists"
        else
            warning "Web directory missing"
            ((ERRORS++))
        fi
    fi

    # Check database
    if [[ $HAS_DB -eq 1 ]] && [[ $SKIP_DB -eq 0 ]]; then
        if ssh "$DST_HOST" "mysql -e 'USE $DNAME' 2>/dev/null"; then
            success "Database accessible: $DNAME"
        else
            warning "Database not accessible: $DNAME"
            ((ERRORS++))
        fi
    fi

    # Check nginx config
    if ssh "$DST_HOST" "test -f /etc/nginx/sites-enabled/$DOMAIN"; then
        success "Nginx config exists"
    else
        warning "Nginx config missing"
        ((ERRORS++))
    fi

    # Check PHP-FPM pool
    if ssh "$DST_HOST" "test -f /etc/php/8.4/fpm/pool.d/$DOMAIN.conf"; then
        success "PHP-FPM pool exists"
    else
        warning "PHP-FPM pool missing"
        ((ERRORS++))
    fi

    if [[ $ERRORS -eq 0 ]]; then
        success "All validation checks passed"
    else
        warning "Validation completed with $ERRORS issue(s)"
    fi
}

# Generate migration report
generate_report() {
    info ""
    info "=================================================="
    info "  Migration Summary for: $DOMAIN"
    info "=================================================="
    info ""
    info "Migrated Components:"
    [[ $HAS_WEB -eq 1 ]] && [[ $SKIP_WEB -eq 0 ]] && info "  ✓ Web files: $SRC_PATH/var/www/html → $DST_PATH/web/app/public"
    [[ $HAS_DB -eq 1 ]] && [[ $SKIP_DB -eq 0 ]] && info "  ✓ Database: $DNAME"
    [[ $SKIP_SSL -eq 0 ]] && info "  ✓ SSL certificates: /etc/ssl/$DOMAIN"
    info "  ✓ NS 3.0 structure created via addvhost"
    info "  ✓ Credentials stored in vconfs table"
    info ""

    # Get destination IP address
    local DST_IP=$(ssh "$DST_HOST" 'hostname -I 2>/dev/null | cut -d" " -f1' 2>/dev/null || echo "203.25.132.7")

    info "Next Steps:"
    info ""
    info "1. If database was migrated, update database credentials in:"
    info "   - WordPress: $DST_PATH/web/app/public/wp-config.php"
    info "   - Laravel: $DST_PATH/web/app/.env"
    info "   - Other apps: Check application config files"
    info ""
    info "2. View vhost configuration:"
    info "   php artisan shvconf $DST_HOST $DOMAIN"
    info ""
    info "3. Update DNS records to point to $DST_HOST ($DST_IP):"
    info "   # Find record IDs first:"
    info "   php artisan shrec --zone=$DOMAIN"
    info "   # Then update each record:"
    info "   php artisan chrec <record_id> --content=$DST_IP"
    info ""
    info "4. Generate SSL certificates on $DST_HOST (after DNS propagates):"
    info "   sx $DST_HOST 'acme.sh --issue -d $DOMAIN -d www.$DOMAIN -w /srv/mail.renta.net/web/app/public'"
    info ""
    info "5. Test the website:"
    info "   curl -k https://$DOMAIN"
    info ""
    info "=================================================="
    info "Log file: $LOG_FILE"
    info "=================================================="
}

# Main execution
main() {
    banner
    info "Starting migration for: $DOMAIN"
    info "Log file: $LOG_FILE"
    [[ $DRY_RUN -eq 1 ]] && warning "DRY-RUN MODE - No changes will be made"
    info ""

    preflight_checks
    read_config
    detect_components

    info ""
    info "=================================================="
    info "  Starting Migration Process"
    info "=================================================="
    info ""

    create_vhost_structure
    migrate_web_files
    migrate_database
    migrate_ssl_certs
    update_vconfs_credentials
    fix_permissions

    if [[ $DRY_RUN -eq 0 ]]; then
        reload_services
    fi

    validate_migration
    generate_report

    success ""
    success "Migration completed successfully!"
    success "Review the summary above for next steps"
}

# Run main
main
