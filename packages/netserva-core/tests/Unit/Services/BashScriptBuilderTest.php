<?php

use NetServa\Core\Services\BashScriptBuilder;

describe('BashScriptBuilder', function () {
    beforeEach(function () {
        $this->builder = new BashScriptBuilder;

        // Sample fully-expanded variables from database (NetServa 3.0 format)
        $this->vars = [
            'VHOST' => 'example.com',
            'VNODE' => 'testserver',
            'UUSER' => 'u1001',
            'U_UID' => '1001',
            'U_GID' => '1001',
            'U_SHL' => '/bin/bash',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/web',
            'MPATH' => '/srv/example.com/msg',
            'VPATH' => '/srv',
            'BPATH' => '/home/backups',
            'APASS' => 'admin_password_123',
            'UPASS' => 'user_password_456',
            'DPASS' => 'db_password_789',
            'EPASS' => 'email_password_abc',
            'WPASS' => 'web_password_def',
            'WUGID' => 'www-data',
            'DNAME' => 'sysadm',
            'DUSER' => 'sysadm',
            'SQCMD' => 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db',
            'C_FPM' => '/etc/php/8.4/fpm',
            'C_WEB' => '/etc/nginx',
            'OSTYP' => 'debian',
            'OSREL' => 'trixie',
            'V_PHP' => '8.4',
        ];
    });

    it('generates complete provisioning script', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toBeString()
            ->toContain('#!/bin/bash')
            ->toContain('set -euo pipefail')
            ->toContain('NetServa 3.0 VHost Provisioning Script');
    });

    it('exports all platform variables', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain("VHOST='example.com'")
            ->toContain("VNODE='testserver'")
            ->toContain("UUSER='u1001'")
            ->toContain("U_UID='1001'")
            ->toContain("WPATH='/srv/example.com/web'");
    });

    it('includes user creation section', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 1. Create system user')
            ->toContain('Step 1: System User')
            ->toContain('useradd')
            ->toContain('User created');
    });

    it('includes database setup section', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 2. Create database entry')
            ->toContain('Step 2: Database Entry')
            ->toContain('INSERT INTO vhosts')
            ->toContain('$SQCMD');
    });

    it('includes directory structure section', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 3. Create directory structure')
            ->toContain('Step 3: Directory Structure')
            ->toContain('mkdir -p "$MPATH"')
            ->toContain('mkdir -p "$WPATH"');
    });

    it('includes PHP-FPM pool configuration', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 4. PHP-FPM pool configuration')
            ->toContain('Step 4: PHP-FPM Pool')
            ->toContain('POOL_DIR')
            ->toContain('[$VHOST]')
            ->toContain('common.conf');
    });

    it('includes nginx vhost configuration', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 5. nginx vhost configuration')
            ->toContain('Step 5: nginx Configuration')
            ->toContain('server {')
            ->toContain('listen 80')
            ->toContain('server_name $VHOST')
            ->toContain('location ~ \.php$')
            ->toContain('fastcgi_pass unix:$WPATH/run/php-fpm.sock')
            ->toContain('sites-available')
            ->toContain('sites-enabled');
    });

    it('includes web files creation', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 6. Create web files')
            ->toContain('Step 6: Web Files')
            ->toContain('index.html')
            ->toContain('phpinfo.php');
    });

    it('includes permissions setup', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 7. Set permissions')
            ->toContain('Step 7: Permissions')
            ->toContain('chown -R "$UUSER:$WUGID" "$UPATH"')
            ->toContain('chmod 755');
    });

    it('includes finalization section', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('# 8. Final commands')
            ->toContain('Step 8: Finalization')
            ->toContain('systemctl reload nginx');
    });

    it('includes summary footer', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('VHost $VHOST provisioned successfully')
            ->toContain('User: $UUSER')
            ->toContain('Path: $UPATH')
            ->toContain('Web:  $WPATH');
    });

    it('properly escapes single quotes in variable values', function () {
        $vars = $this->vars;
        $vars['VHOST'] = "test'domain.com";

        $script = $this->builder->build($vars);

        // Single quotes should be escaped as '\''
        expect($script)->toContain("VHOST='test'\\''domain.com'");
    });

    it('handles admin user (UID 1000) with sudo group', function () {
        $vars = $this->vars;
        $vars['U_UID'] = '1000';

        $script = $this->builder->build($vars);

        expect($script)->toContain('-G sudo,adm');
    });

    it('handles non-admin user (UID > 1000) without sudo', function () {
        $vars = $this->vars;
        $vars['U_UID'] = '1002';

        $script = $this->builder->build($vars);

        expect($script)->not->toContain('-G sudo');
    });

    it('handles Alpine/Manjaro PHP-FPM path differences', function () {
        $alpineVars = $this->vars;
        $alpineVars['OSTYP'] = 'alpine';

        $script = $this->builder->build($alpineVars);

        expect($script)->toContain('POOL_DIR="$C_FPM/php-fpm.d"');
    });

    it('handles Debian/Ubuntu PHP-FPM path', function () {
        $debianVars = $this->vars;
        $debianVars['OSTYP'] = 'debian';

        $script = $this->builder->build($debianVars);

        expect($script)->toContain('POOL_DIR="$C_FPM/pool.d"');
    });

    it('creates valid bash script with no syntax errors', function () {
        $script = $this->builder->build($this->vars);

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'netserva_test_');
        file_put_contents($tmpFile, $script);

        // Test bash syntax
        $output = [];
        $returnCode = 0;
        exec("bash -n {$tmpFile} 2>&1", $output, $returnCode);

        unlink($tmpFile);

        expect($returnCode)->toBe(0, 'Generated bash script should have no syntax errors: '.implode("\n", $output));
    });

    it('generates idempotent script that can be run multiple times', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('if id -u "$UUSER"')
            ->toContain('already exists')
            ->toContain('if [[ -d "$UPATH" ]]')
            ->toContain('if [[ -f "$WPATH/index.html"');
    });

    it('includes error handling with set -euo pipefail', function () {
        $script = $this->builder->build($this->vars);

        expect($script)
            ->toContain('set -euo pipefail');
    });

    it('generates script with proper section separation', function () {
        $script = $this->builder->build($this->vars);

        $sectionMarkers = [
            '# 1. Create system user',
            '# 2. Create database entry',
            '# 3. Create directory structure',
            '# 4. PHP-FPM pool configuration',
            '# 5. nginx vhost configuration',
            '# 6. Create web files',
            '# 7. Set permissions',
            '# 8. Final commands',
        ];

        foreach ($sectionMarkers as $marker) {
            expect($script)->toContain($marker);
        }
    });
});
