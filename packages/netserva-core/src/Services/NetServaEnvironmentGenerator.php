<?php

namespace NetServa\Core\Services;

use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * NetServa Environment Variable Generator
 *
 * Replicates NetServa 1.0 sethost() and gethost() bash functions
 * Generates all 53+ environment variables for a vhost
 *
 * Created: 20250107 - Updated: 20250107
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class NetServaEnvironmentGenerator
{
    /**
     * Generate all environment variables for a vhost
     *
     * @param  FleetVnode  $vnode  The VNode (server)
     * @param  string  $domain  The domain/vhost name
     * @param  array  $overrides  Optional values to override defaults
     * @param  array|null  $detectedOs  OS info from detectRemoteOs() (OSTYP, OSREL, OSMIR)
     * @return array All 53+ environment variables
     */
    public function generate(FleetVnode $vnode, string $domain, array $overrides = [], ?array $detectedOs = null): array
    {
        // Step 1: Static defaults (can be overridden)
        $vars = $this->getStaticDefaults($vnode);

        // Step 2: Apply detected OS (from /etc/os-release)
        if ($detectedOs) {
            $vars['OSTYP'] = $detectedOs['OSTYP'];
            $vars['OSREL'] = $detectedOs['OSREL'];
            $vars['OSMIR'] = $detectedOs['OSMIR'];
        }

        // Step 3: Apply user overrides
        $vars = array_merge($vars, $overrides);

        // Step 4: Dynamic variables (calculated from static + domain)
        $vars = array_merge($vars, $this->getDynamicVariables($vnode, $domain, $vars));

        // Step 5: OS-specific overrides (based on detected or default OSTYP)
        $vars = array_merge($vars, $this->getOsOverrides($vnode, $vars));

        // Step 6: Final calculated variables (depend on previous steps)
        $vars = array_merge($vars, $this->getFinalCalculations($vars));

        return $vars;
    }

    /**
     * Static environment variable defaults
     */
    protected function getStaticDefaults(FleetVnode $vnode): array
    {
        $fqdn = $vnode->name.'.'.($vnode->domain ?? 'local');

        return [
            // Admin user settings
            'ADMIN' => 'sysadm',
            'A_GID' => '1000',
            'A_UID' => '1000',
            'ANAME' => 'System Administrator',

            // Base paths
            'BPATH' => '/home/backups',
            'VPATH' => '/srv',

            // Config paths
            'CIMAP' => '/etc/dovecot',
            'CSMTP' => '/etc/postfix',
            'C_DNS' => '/etc/powerdns',
            'C_SQL' => '/etc/mysql',
            'C_SSL' => '/etc/ssl',
            'C_WEB' => '/etc/nginx',

            // Database paths
            'DBMYS' => '/var/lib/mysql',
            'DBSQL' => '/var/lib/sqlite',

            // Database connection
            'DHOST' => 'localhost',
            'DPORT' => '3306',
            'DTYPE' => 'mysql',

            // OS defaults (Debian Trixie)
            'OSMIR' => 'deb.debian.org',
            'OSREL' => 'trixie',
            'OSTYP' => 'debian',

            // DNS Provider (from vnode.dns_provider_id)
            'DPVDR' => $vnode->dnsProvider->name ?? 'homelab',

            // Timezone
            'TAREA' => 'Australia',
            'TCITY' => 'Sydney',

            // PHP version (OS-specific, may be overridden)
            'V_PHP' => '8.3',

            // Web server
            'VUSER' => 'admin',
            'WUGID' => 'www-data',
        ];
    }

    /**
     * Dynamic variables calculated from domain and static vars
     */
    protected function getDynamicVariables(FleetVnode $vnode, string $domain, array $vars): array
    {
        // AHOST = Server's FQDN (from hostname -f on remote, stored in vnode->fqdn)
        // This is the administrative hostname of the server itself
        $ahost = $vnode->fqdn ?? ($vnode->name.'.local');

        // Calculate UID (1000 for admin host, otherwise generate new)
        $isAdminHost = ($ahost === $domain);
        $uUid = $isAdminHost ? $vars['A_UID'] : $this->generateNewUid($vnode);
        $uUser = $isAdminHost ? $vars['ADMIN'] : 'u'.$uUid;

        // Extract hostname parts
        $parts = explode('.', $domain);
        $hName = $parts[0];
        $hDomain = implode('.', array_slice($parts, 1)) ?: $domain;

        // Calculate IP address (from vnode)
        $ip4_0 = $vnode->ip_address ?? '127.0.0.1';

        // Mail host logic
        $mHost = ($hName === 'mail') ? $domain : $domain;

        return [
            'VHOST' => $domain,
            'VNODE' => $vnode->name,

            // User
            'UUSER' => $uUser,
            'U_UID' => (string) $uUid,
            'U_GID' => (string) $uUid,
            'U_SHL' => $isAdminHost ? '/bin/bash' : '/bin/sh',

            // Hostname parts
            'HNAME' => $hName,
            'HDOMN' => $hDomain,

            // Hosts
            'AHOST' => $ahost,
            'MHOST' => $mHost,

            // Network
            'IP4_0' => $ip4_0,

            // Generated passwords (16 chars alphanumeric)
            'APASS' => $this->generatePassword(),
            'DPASS' => $this->generatePassword(),
            'EPASS' => $this->generatePassword(),
            'UPASS' => $this->generatePassword(),
            'WPASS' => $this->generatePassword(),

            // WordPress random username (6 chars lowercase)
            'WPUSR' => $this->generateRandomString(6, 'a-z'),
        ];
    }

    /**
     * Final calculations that depend on previous variables
     */
    protected function getFinalCalculations(array $vars): array
    {
        $admin = $vars['ADMIN'];
        $vhost = $vars['VHOST'];
        $uUser = $vars['UUSER'];
        $vPath = $vars['VPATH'];
        $dbSql = $vars['DBSQL'];
        $dType = $vars['DTYPE'];

        // Paths
        $uPath = "$vPath/$vhost";
        $wPath = "$uPath/web";
        $mPath = "$uPath/msg";
        $dPath = "$dbSql/$admin/$admin.db";

        // Database name (replace . and - with _)
        $dName = ($uUser === $admin) ? $admin : str_replace(['.', '-'], '_', $vhost);

        // Database user
        $dUser = $uUser;

        // Admin email
        $vUser = $vars['VUSER'];
        $aMailDomain = preg_replace('/^mail\./', '', $vhost);
        $aMail = "$vUser@$aMailDomain";

        // SQL commands
        $exMys = "mariadb -BN $admin";
        $exSql = "sqlite3 $dPath";
        $sqCmd = ($dType === 'mysql') ? $exMys : $exSql;
        $sqDns = ($dType === 'mysql') ? 'mariadb -BN pdns' : "sqlite3 $dbSql/$admin/pdns.db";

        // PHP-FPM config path
        $vPhp = $vars['V_PHP'];
        $cFpm = "/etc/php/$vPhp/fpm";

        return [
            // Paths
            'UPATH' => $uPath,
            'WPATH' => $wPath,
            'MPATH' => $mPath,
            'DPATH' => $dPath,

            // Database
            'DNAME' => $dName,
            'DUSER' => $dUser,

            // Mail
            'AMAIL' => $aMail,

            // SQL Commands
            'EXMYS' => $exMys,
            'EXSQL' => $exSql,
            'SQCMD' => $sqCmd,
            'SQDNS' => $sqDns,

            // PHP-FPM
            'C_FPM' => $cFpm,
        ];
    }

    /**
     * OS-specific overrides
     */
    protected function getOsOverrides(FleetVnode $vnode, array $vars): array
    {
        $osType = $vars['OSTYP'];

        return match ($osType) {
            'alpine', 'linux-musl' => [
                'V_PHP' => '84',
                'C_DNS' => '/etc/pdns',
                'C_FPM' => '/etc/php84',
                'C_SQL' => '/etc/my.cnf.d',
                'EXMYS' => 'mariadb -BN '.$vars['ADMIN'],
                'OSMIR' => 'dl-cdn.alpinelinux.org',
                'OSREL' => 'latest-stable',
                'WUGID' => 'nginx',
            ],
            'debian' => [
                'V_PHP' => '8.2',
                'OSMIR' => 'deb.debian.org',
                'OSREL' => 'trixie',
            ],
            'manjaro' => [
                'V_PHP' => '8.4',
                'C_DNS' => '/etc/powerdns',
                'C_FPM' => '/etc/php',
                'C_SQL' => '/etc/my.cnf.d',
                'OSMIR' => 'manjaro.moson.eu',
                'OSREL' => 'stable',
                'WUGID' => 'http',
            ],
            'cachyos', 'arch' => [
                'V_PHP' => '8.4',
                'C_DNS' => '/etc/powerdns',
                'C_FPM' => '/etc/php',
                'C_SQL' => '/etc/my.cnf.d',
                'OSMIR' => 'archlinux.cachyos.org',
                'OSREL' => 'n/a',
                'WUGID' => 'http',
            ],
            'ubuntu' => [
                'V_PHP' => '8.3',
                'OSMIR' => 'archive.ubuntu.com',
                'OSREL' => 'noble',
            ],
            default => [],
        };
    }

    /**
     * Detect OS type from FleetVnode
     */
    protected function detectOsType(FleetVnode $vnode): string
    {
        // Try to get from vnode metadata
        if (isset($vnode->metadata['os_type'])) {
            return $vnode->metadata['os_type'];
        }

        // Try to detect from os_version field
        if ($vnode->os_version) {
            $osVersion = strtolower($vnode->os_version);
            if (str_contains($osVersion, 'alpine')) {
                return 'alpine';
            }
            if (str_contains($osVersion, 'debian')) {
                return 'debian';
            }
            if (str_contains($osVersion, 'ubuntu')) {
                return 'ubuntu';
            }
            if (str_contains($osVersion, 'arch') || str_contains($osVersion, 'cachy')) {
                return 'cachyos';
            }
            if (str_contains($osVersion, 'manjaro')) {
                return 'manjaro';
            }
        }

        // Default
        return 'ubuntu';
    }

    /**
     * Generate secure random password (alphanumeric, 16 chars)
     */
    protected function generatePassword(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }

    /**
     * Generate random string with custom character set
     */
    protected function generateRandomString(int $length, string $charSet): string
    {
        $chars = match ($charSet) {
            'a-z' => 'abcdefghijklmnopqrstuvwxyz',
            'A-Z' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'A-Za-z' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
            '0-9' => '0123456789',
            default => $charSet,
        };

        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }

    /**
     * Generate new UID for non-admin users
     */
    protected function generateNewUid(FleetVnode $vnode): int
    {
        // Get highest existing UID from database
        $maxUid = FleetVhost::where('vnode_id', $vnode->id)
            ->whereNotNull('environment_vars->U_UID')
            ->get()
            ->map(fn ($vhost) => (int) ($vhost->environment_vars['U_UID'] ?? 0))
            ->max();

        // Start at 1000 if none exist, otherwise increment
        return max(1000, $maxUid + 1);
    }

    /**
     * Get minimal variable set (subset for testing/simple configs)
     */
    public function getMinimalVariables(FleetVnode $vnode, string $domain, ?array $detectedOs = null): array
    {
        $full = $this->generate($vnode, $domain, [], $detectedOs);

        // Return only essential variables
        return array_intersect_key($full, array_flip([
            'VHOST', 'VNODE', 'UUSER', 'U_UID', 'U_GID',
            'UPATH', 'WPATH', 'MPATH',
            'DNAME', 'DUSER', 'DPASS',
            'V_PHP', 'WUGID',
        ]));
    }
}
