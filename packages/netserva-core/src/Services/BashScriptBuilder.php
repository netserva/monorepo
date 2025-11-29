<?php

namespace NetServa\Core\Services;

/**
 * Bash Script Builder - Pure PHP Script Generation
 *
 * Generates bash provisioning scripts from database-stored variables.
 * NO templates - pure PHP string building for maximum flexibility.
 *
 * NetServa 3.0 Database-First Architecture:
 * - All variables fully expanded (no $VAR references in input)
 * - Generated script declares variables at top
 * - Script body uses bash $VAR references (normal bash)
 *
 * Created: 20250109
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class BashScriptBuilder
{
    /**
     * Build complete provisioning script
     *
     * @param  array  $vars  Fully expanded platform variables from database
     * @return string Complete bash script ready for execution
     */
    public function build(array $vars): string
    {
        return implode("\n\n", [
            $this->generateHeader($vars),
            $this->exportVariables($vars),
            $this->generateUserCreation($vars),
            $this->generateDatabaseSetup($vars),
            $this->generateDirectoryStructure($vars),
            $this->generatePhpFpmConfig($vars),
            $this->generateNginxConfig($vars),
            $this->generateWebFiles($vars),
            $this->generatePermissions($vars),
            $this->generateFinalization($vars),
            $this->generateFooter($vars),
        ]);
    }

    /**
     * Generate script header
     */
    protected function generateHeader(array $v): string
    {
        $vhost = $v['VHOST'];
        $vnode = $v['VNODE'];
        $timestamp = date('Y-m-d H:i:s');

        return <<<BASH
        #!/bin/bash
        # NetServa 3.0 VHost Provisioning Script
        # Generated: {$timestamp}
        # Domain: {$vhost}
        # VNode: {$vnode}

        set -euo pipefail

        echo "=== NetServa 3.0 VHost Provisioning: {$vhost} ==="
        BASH;
    }

    /**
     * Export all platform variables at top of script
     *
     * Variables are fully expanded - script just declares them for use
     */
    protected function exportVariables(array $vars): string
    {
        $lines = ['# Platform Variables (fully expanded from database)'];

        foreach ($vars as $name => $value) {
            // Escape single quotes in values
            $escaped = str_replace("'", "'\\''", $value);
            $lines[] = "{$name}='{$escaped}'";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate user creation section
     */
    protected function generateUserCreation(array $v): string
    {
        $groupsFlag = ($v['U_UID'] === '1000') ? '-G sudo,adm' : '';
        $passwordCmd = ($v['UPASS'] !== $v['APASS'])
            ? 'echo "$UUSER:$UPASS" | chpasswd'
            : '# User password same as admin, skipping';

        // Generate useradd command with optional groups
        $useraddCmd = $groupsFlag
            ? "useradd -M -U {$groupsFlag} -s \"\$U_SHL\" -u \"\$U_UID\" -d \"\$UPATH\" -c \"\$VHOST\" \"\$UUSER\""
            : 'useradd -M -U -s "$U_SHL" -u "$U_UID" -d "$UPATH" -c "$VHOST" "$UUSER"';

        return <<<BASH
        # 1. Create system user
        echo ">>> Step 1: System User"
        if id -u "\$UUSER" &>/dev/null; then
            echo "    ✓ User \$UUSER already exists (UID: \$U_UID)"
        else
            # Create sudo group if needed (for UID 1000)
            [[ \$(getent group sudo) ]] || groupadd -r sudo

            # Create user
            echo "    → Creating user \$UUSER (UID: \$U_UID)"
            {$useraddCmd}

            {$passwordCmd}

            echo "    ✓ User created: \$UUSER"
        fi
        BASH;
    }

    /**
     * Generate database setup section
     */
    protected function generateDatabaseSetup(array $v): string
    {
        return <<<'BASH'
        # 2. Create database entry
        echo ">>> Step 2: Database Entry"
        if command -v mysql &>/dev/null || command -v mariadb &>/dev/null; then
            VHOST_COUNT=$(echo "SELECT COUNT(id) FROM vhosts WHERE domain = '$VHOST'" | $SQCMD 2>/dev/null || echo "0")

            if [[ "$VHOST_COUNT" == "0" ]]; then
                echo "    → Creating database entry for $VHOST"
                echo "INSERT INTO vhosts (domain, uid, gid, active, created_at, updated_at) VALUES ('$VHOST', $U_UID, $U_GID, 1, NOW(), NOW())" | $SQCMD
                echo "    ✓ Database entry created"
            else
                echo "    ✓ Database entry already exists"
            fi
        else
            echo "    ⚠ MySQL/MariaDB not found, skipping database entry"
        fi
        BASH;
    }

    /**
     * Generate directory structure section
     */
    protected function generateDirectoryStructure(array $v): string
    {
        return <<<'BASH'
        # 3. Create directory structure (NetServa 3.0 - Web-centric, No SSH)
        echo ">>> Step 3: Directory Structure"
        if [[ -d "$UPATH" ]]; then
            echo "    ✓ Base path $UPATH already exists"
        else
            echo "    → Creating directory structure"
            # NetServa 3.0: /srv/{domain}/{msg,web}
            # Web subdirs: /srv/{domain}/web/{app,log,run,app/public}
            mkdir -p "$MPATH"
            mkdir -p "$WPATH"/{app/public,log,run}
            echo "    ✓ Directories created"
        fi
        BASH;
    }

    /**
     * Generate PHP-FPM pool configuration section
     */
    protected function generatePhpFpmConfig(array $v): string
    {
        return <<<'BASH'
        # 4. PHP-FPM pool configuration
        echo ">>> Step 4: PHP-FPM Pool"
        if [[ -d "$C_FPM" ]]; then
            # Determine pool directory based on OS
            if [[ "$OSTYP" == "alpine" ]] || [[ "$OSTYP" == "manjaro" ]] || [[ "$OSTYP" == "cachyos" ]]; then
                POOL_DIR="$C_FPM/php-fpm.d"
            else
                POOL_DIR="$C_FPM/pool.d"
            fi

            # Create pool config if not exists
            if [[ ! -f "$POOL_DIR/$VHOST.conf" ]]; then
                echo "    → Creating PHP-FPM pool"
                cat > "$POOL_DIR/$VHOST.conf" <<POOLEOF
        [$VHOST]
        user = $U_UID
        group = $U_GID
        include = $C_FPM/common.conf
        POOLEOF
                echo "    ✓ PHP-FPM pool created"

                # Move default www.conf if exists
                [[ -f "$POOL_DIR/www.conf" ]] && mv "$POOL_DIR/www.conf" "$C_FPM/" && echo "    ✓ Moved www.conf out of pool.d"
            else
                echo "    ✓ PHP-FPM pool already exists"
            fi
        else
            echo "    ⚠ PHP-FPM not found at $C_FPM, skipping"
        fi
        BASH;
    }

    /**
     * Generate nginx vhost configuration section
     *
     * NetServa 3.0 Pattern:
     * - Creates config directly in /etc/nginx/sites-enabled/ (no sites-available symlink)
     * - Uses /etc/nginx/common.conf for shared settings
     * - WWW redirect to non-WWW
     * - HTTP-only by default (SSL added manually via acme.sh)
     */
    protected function generateNginxConfig(array $v): string
    {
        return <<<'BASH'
        # 5. nginx vhost configuration
        echo ">>> Step 5: nginx Configuration"
        if [[ -d "$C_WEB" ]]; then
            NGINX_CONF="$C_WEB/sites-enabled/$VHOST"

            if [[ ! -f "$NGINX_CONF" ]]; then
                echo "    → Creating nginx vhost config"
                cat > "$NGINX_CONF" <<NGINXEOF
        server {
            listen                      80;
            server_name                 www.$VHOST;
            return 301                  http://$VHOST\$request_uri;
        }
        server {
            listen                      80;
            server_name                 $VHOST;
            include                     /etc/nginx/common.conf;
        }
        NGINXEOF
                echo "    ✓ nginx config created"

                # Test nginx config
                if nginx -t &>/dev/null; then
                    echo "    ✓ nginx config valid"
                else
                    echo "    ⚠ nginx config test failed (will try reload anyway)"
                fi
            else
                echo "    ✓ nginx config already exists"
            fi
        else
            echo "    ⚠ nginx not found at $C_WEB, skipping"
        fi
        BASH;
    }

    /**
     * Generate web files section
     */
    protected function generateWebFiles(array $v): string
    {
        return <<<'BASH'
        # 6. Create web files
        echo ">>> Step 6: Web Files"
        if [[ -f "$WPATH/index.html" || -f "$WPATH/index.php" ]]; then
            echo "    ✓ Web files already exist"
        else
            echo "    → Creating index.html"
            cat > "$WPATH/index.html" <<HTMLEOF
        <!DOCTYPE html><title>$VHOST</title><h1 style="text-align:center">$VHOST</h1>
        HTMLEOF
            echo "    ✓ index.html created"
        fi

        if [[ ! -f "$WPATH/phpinfo.php" ]]; then
            echo "    → Creating phpinfo.php"
            cat > "$WPATH/phpinfo.php" <<'PHPEOF'
        <?php error_log(__FILE__.' '.$_SERVER['REMOTE_ADDR']); phpinfo();
        PHPEOF
            echo "    ✓ phpinfo.php created"
        fi
        BASH;
    }

    /**
     * Generate permissions section
     */
    protected function generatePermissions(array $v): string
    {
        return <<<'BASH'
        # 7. Set permissions
        echo ">>> Step 7: Permissions"
        echo "    → Setting ownership: $UUSER:$WUGID"
        chown -R "$UUSER:$WUGID" "$UPATH"
        chmod 755 "$UPATH"
        chmod 755 "$WPATH"
        chmod 755 "$WPATH/app"
        chmod 755 "$WPATH/app/public"
        chmod 750 "$WPATH/log"
        chmod 750 "$WPATH/run"
        echo "    ✓ Permissions set"
        BASH;
    }

    /**
     * Generate finalization section
     */
    protected function generateFinalization(array $v): string
    {
        return <<<'BASH'
        # 8. Final commands (if shell functions are available)
        echo ">>> Step 8: Finalization"
        if [[ -f ~/.rc/_shrc ]]; then
            source ~/.rc/_shrc

            # Update logging
            if command -v logging &>/dev/null; then
                logging "$VHOST" update >/dev/null 2>&1 && echo "    ✓ Logging updated"
            fi

            # Set shell password
            if command -v chshpw &>/dev/null; then
                chshpw "$UUSER" "$UPASS" && echo "    ✓ Shell password set"
            fi

            # Fix permissions
            if command -v chperms &>/dev/null; then
                chperms "$VHOST" && echo "    ✓ Permissions fixed via chperms"
            fi

            # Restart web service
            if command -v serva &>/dev/null; then
                serva restart web && echo "    ✓ Services restarted via serva"
            else
                systemctl reload nginx php*-fpm 2>/dev/null && echo "    ✓ Services reloaded"
            fi
        else
            # Fallback: restart services directly
            echo "    → Reloading services"
            systemctl reload nginx 2>/dev/null && echo "    ✓ nginx reloaded"
            systemctl reload php*-fpm 2>/dev/null && echo "    ✓ php-fpm reloaded"
        fi
        BASH;
    }

    /**
     * Generate script footer
     */
    protected function generateFooter(array $v): string
    {
        return <<<'BASH'
        echo ""
        echo "=== ✓ VHost $VHOST provisioned successfully ==="
        echo "    User: $UUSER (UID: $U_UID)"
        echo "    Path: $UPATH"
        echo "    Web:  $WPATH"
        echo "    Mail: $MPATH"
        BASH;
    }
}
