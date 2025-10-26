#!/bin/bash
# Migrate acme.sh SSL certificates to /etc/ssl/<domain>/
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
ACME_DIR="/root/.acme.sh"
SSL_DIR="/etc/ssl"
MIGRATION_LOG="/var/log/ssl_cert_migration.log"

# Create log
touch "$MIGRATION_LOG"
exec > >(tee -a "$MIGRATION_LOG") 2>&1

log_info "Starting SSL certificate migration"
log_info "Date: $(date)"
log_info "Source: $ACME_DIR"
log_info "Destination: $SSL_DIR"
echo "---"

# Check if acme.sh directory exists
if [[ ! -d "$ACME_DIR" ]]; then
    log_error "acme.sh directory not found at $ACME_DIR"
    exit 1
fi

# Get list of certificate directories (exclude acme.sh itself and ca directories)
CERT_DIRS=$(find "$ACME_DIR" -maxdepth 1 -type d -name "*_ecc" -o -name "*.???" | \
    grep -v "^$ACME_DIR$" | \
    grep -v "/ca$" | \
    sort)

if [[ -z "$CERT_DIRS" ]]; then
    log_warn "No certificate directories found in $ACME_DIR"
    exit 0
fi

log_info "Found certificate directories:"
echo "$CERT_DIRS" | while read dir; do
    echo "  - $(basename "$dir")"
done
echo ""

# Migrate each certificate
MIGRATED=0
SKIPPED=0
FAILED=0

for CERT_DIR in $CERT_DIRS; do
    # Extract domain name
    BASENAME=$(basename "$CERT_DIR")
    DOMAIN="${BASENAME%_ecc}"  # Remove _ecc suffix if present

    log_info "Processing: $DOMAIN"

    DEST_DIR="$SSL_DIR/$DOMAIN"

    # Create destination directory
    if [[ -d "$DEST_DIR" ]]; then
        log_warn "  Destination $DEST_DIR already exists"
        OVERWRITE=0
    else
        log_info "  Creating $DEST_DIR"
        mkdir -p "$DEST_DIR"
        chmod 755 "$DEST_DIR"
        OVERWRITE=1
    fi

    # Copy certificate files
    COPIED=0

    # fullchain.cer → fullchain.pem
    if [[ -f "$CERT_DIR/fullchain.cer" ]]; then
        log_info "  Copying fullchain.cer → fullchain.pem"
        cp "$CERT_DIR/fullchain.cer" "$DEST_DIR/fullchain.pem"
        chmod 644 "$DEST_DIR/fullchain.pem"
        ((COPIED++))
    else
        log_warn "  fullchain.cer not found in $CERT_DIR"
    fi

    # <domain>.key → key.pem
    if [[ -f "$CERT_DIR/$DOMAIN.key" ]]; then
        log_info "  Copying $DOMAIN.key → key.pem"
        cp "$CERT_DIR/$DOMAIN.key" "$DEST_DIR/key.pem"
        chmod 600 "$DEST_DIR/key.pem"
        ((COPIED++))
    else
        log_warn "  $DOMAIN.key not found in $CERT_DIR"
    fi

    # ca.cer → cert.pem (CA certificate)
    if [[ -f "$CERT_DIR/ca.cer" ]]; then
        log_info "  Copying ca.cer → cert.pem"
        cp "$CERT_DIR/ca.cer" "$DEST_DIR/cert.pem"
        chmod 644 "$DEST_DIR/cert.pem"
        ((COPIED++))
    else
        log_warn "  ca.cer not found in $CERT_DIR"
    fi

    # Also copy the certificate itself if it exists
    if [[ -f "$CERT_DIR/$DOMAIN.cer" ]]; then
        log_info "  Copying $DOMAIN.cer → domain.pem"
        cp "$CERT_DIR/$DOMAIN.cer" "$DEST_DIR/domain.pem"
        chmod 644 "$DEST_DIR/domain.pem"
        ((COPIED++))
    fi

    # Verify migration
    log_info "  Verifying certificates..."
    if [[ -f "$DEST_DIR/fullchain.pem" ]] && [[ -f "$DEST_DIR/key.pem" ]]; then
        # Test certificate validity
        if openssl x509 -in "$DEST_DIR/fullchain.pem" -noout -text &>/dev/null; then
            log_info "  ✓ Certificate valid"
            EXPIRY=$(openssl x509 -in "$DEST_DIR/fullchain.pem" -noout -enddate | cut -d= -f2)
            log_info "    Expires: $EXPIRY"
            ((MIGRATED++))
        else
            log_error "  Certificate validation failed"
            ((FAILED++))
        fi
    else
        log_error "  Required files missing (fullchain.pem or key.pem)"
        ((FAILED++))
    fi

    echo ""
done

# Update acme.sh to deploy to /etc/ssl on renewal
log_info "Creating acme.sh deploy hook for future renewals..."
cat > "$ACME_DIR/deploy/ssl_dir.sh" << 'DEPLOY_HOOK'
#!/bin/bash
# acme.sh deploy hook to copy certs to /etc/ssl/<domain>/

DOMAIN="$1"
KEY_PATH="$2"
CERT_PATH="$3"
CA_PATH="$4"
FULLCHAIN_PATH="$5"

SSL_DIR="/etc/ssl/$DOMAIN"

mkdir -p "$SSL_DIR"
cp "$FULLCHAIN_PATH" "$SSL_DIR/fullchain.pem"
cp "$KEY_PATH" "$SSL_DIR/key.pem"
cp "$CA_PATH" "$SSL_DIR/cert.pem"
chmod 644 "$SSL_DIR/fullchain.pem" "$SSL_DIR/cert.pem"
chmod 600 "$SSL_DIR/key.pem"

# Reload nginx if running
systemctl is-active nginx &>/dev/null && systemctl reload nginx
DEPLOY_HOOK

chmod +x "$ACME_DIR/deploy/ssl_dir.sh"
log_info "Deploy hook created at $ACME_DIR/deploy/ssl_dir.sh"

# Summary
echo "---"
log_info "Migration Summary:"
log_info "  Migrated: $MIGRATED certificates"
log_info "  Skipped:  $SKIPPED certificates"
log_info "  Failed:   $FAILED certificates"

if [[ $FAILED -gt 0 ]]; then
    log_error "Some migrations failed! Check log: $MIGRATION_LOG"
    exit 1
fi

log_info "SSL certificate migration completed successfully!"
log_info "Certificates copied to $SSL_DIR/<domain>/"
log_info "acme.sh will auto-deploy to $SSL_DIR on renewal"
log_info "Log file: $MIGRATION_LOG"
