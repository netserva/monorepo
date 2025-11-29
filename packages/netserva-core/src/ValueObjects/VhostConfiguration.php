<?php

namespace NetServa\Core\ValueObjects;

/**
 * VHost Configuration Value Object
 *
 * Type-safe representation of a complete NetServa VHost configuration.
 * Replaces the 53 bash environment variables with structured PHP objects.
 */
readonly class VhostConfiguration
{
    public function __construct(
        public string $VHOST,   // Domain name - matches $VHOST
        public string $VNODE,   // SSH host identifier - matches $VNODE
        public int $U_UID,      // User UID - matches $U_UID
        public int $U_GID,      // User GID - matches $U_GID
        public string $UUSER,   // Username - matches $UUSER
        public VhostPasswords $passwords,
        public VhostPaths $paths,
        public OsConfiguration $osConfig,
        public string $IP4_0 = '127.0.0.1'  // Server IP - matches $IP4_0
    ) {}

    /**
     * Convert to the canonical 54 NetServa environment variables
     * Simplified from 56 (removed enterprise bloat: HNODE, VTECH, STACK)
     * Added VNODE for critical SSH host identification
     */
    public function toEnvironmentArray(): array
    {
        $domainParts = explode('.', $this->VHOST);
        $HNAME = $domainParts[0];
        $HDOMN = count($domainParts) >= 2 ? implode('.', array_slice($domainParts, 1)) : $this->VHOST;

        // Mail host logic: if subdomain is 'mail', use domain as mail host
        $MHOST = ($HNAME === 'mail') ? $this->VHOST : $this->VHOST;

        // Database name: replace dots and dashes with underscores
        $DNAME = ($this->UUSER === 'sysadm') ? 'sysadm' : str_replace(['.', '-'], '_', $this->VHOST);

        // Shell: admin gets bash, others get sh
        $U_SHL = ($this->U_UID === 1000) ? '/bin/bash' : '/bin/sh';

        // Web user/group based on OS
        $WUGID = $this->osConfig->type->getWebUserGroup();

        // PHP version based on OS
        $V_PHP = $this->osConfig->type->getPhpVersion();

        return [
            'ADMIN' => 'sysadm',
            'AHOST' => $this->VNODE,
            'AMAIL' => "admin@{$HDOMN}",
            'ANAME' => 'System Administrator',
            'APASS' => $this->passwords->admin,
            'A_GID' => '1000',
            'A_UID' => '1000',
            'BPATH' => $this->paths->bpath,
            'CIMAP' => $this->paths->dovecotPath,
            'CSMTP' => $this->paths->postfixPath,
            'C_DNS' => $this->paths->dnsPath,
            'C_FPM' => $this->paths->phpFpmPath,
            'C_SQL' => $this->paths->mysqlPath,
            'C_SSL' => $this->paths->sslPath,
            'C_WEB' => $this->paths->nginxPath,
            'DBMYS' => '/var/lib/mysql',
            'DBSQL' => '/var/lib/sqlite',
            'DHOST' => 'localhost',
            'DNAME' => $DNAME,
            'DPASS' => $this->passwords->database,
            'DPATH' => $this->paths->dbpath,
            'DPORT' => '3306',
            'DTYPE' => 'mysql',
            'DUSER' => $this->UUSER,
            'EPASS' => $this->passwords->email,
            'EXMYS' => $this->getSqlCommand(),
            'EXSQL' => "sqlite3 {$this->paths->dbpath}",
            'HDOMN' => $HDOMN,
            'HNAME' => $HNAME,
            'IP4_0' => $this->IP4_0,
            'MHOST' => $MHOST,
            'MPATH' => $this->paths->mpath,
            'OSMIR' => $this->osConfig->mirror,
            'OSREL' => $this->osConfig->release,
            'OSTYP' => $this->osConfig->type->value,
            'VNODE' => $this->VNODE,
            'SQCMD' => $this->getSqlCommand(),
            'SQDNS' => 'mariadb -BN pdns',
            'TAREA' => 'Australia',
            'TCITY' => 'Sydney',
            'UPASS' => $this->passwords->user,
            'UPATH' => $this->paths->upath,
            'UUSER' => $this->UUSER,
            'U_GID' => (string) $this->U_GID,
            'U_SHL' => $U_SHL,
            'U_UID' => (string) $this->U_UID,
            'VHOST' => $this->VHOST,
            'VPATH' => $this->paths->vpath,
            'VUSER' => 'admin',
            'V_PHP' => $V_PHP,
            'WPASS' => $this->passwords->web,
            'WPATH' => $this->paths->wpath,
            'WPUSR' => $this->passwords->wordpress,
            'WUGID' => $WUGID,
        ];
    }

    /**
     * Get SQL command based on vnode's database type
     */
    protected function getSqlCommand(): string
    {
        // Load vnode to check database type
        $vnode = \NetServa\Fleet\Models\FleetVnode::where('name', $this->VNODE)->first();

        // DEBUG logging
        \Illuminate\Support\Facades\Log::info('DEBUG getSqlCommand()', [
            'VNODE' => $this->VNODE,
            'vnode_found' => $vnode ? 'yes' : 'no',
            'database_type' => $vnode->database_type ?? 'NULL',
            'will_use_sqlite' => (! $vnode || $vnode->database_type === 'sqlite') ? 'yes' : 'no',
        ]);

        if (! $vnode || $vnode->database_type === 'sqlite') {
            return "sqlite3 {$this->paths->dbpath}/sysadm.db";
        }

        // For MySQL/MariaDB, use mariadb command (uses .my.cnf for auth)
        return 'mariadb -BN sysadm';
    }

    /**
     * Export as NetServa-compatible shell environment format
     */
    public function toShellFormat(): string
    {
        $vars = $this->toEnvironmentArray();
        $output = [];

        foreach ($vars as $key => $value) {
            $output[] = "{$key}='{$value}'";
        }

        return implode("\n", $output);
    }

    /**
     * Get credentials for separate credentials file
     */
    public function getCredentials(): array
    {
        return [
            'admin_password' => $this->passwords->admin,
            'database_password' => $this->passwords->database,
            'email_password' => $this->passwords->email,
            'user_password' => $this->passwords->user,
            'web_password' => $this->passwords->web,
            'wordpress_user' => $this->passwords->wordpress,
        ];
    }

    /**
     * Get NS environment variables for NetServa platform paths
     * These are used throughout NetServa scripts alongside VHost variables
     */
    public function getNetServaEnvironmentVars(): array
    {
        $nsPaths = NetServaPaths::fromEnvironment();

        return $nsPaths->toEnvironmentArray();
    }

    /**
     * Get complete environment including both VHost and NS variables
     */
    public function getCompleteEnvironment(): array
    {
        return array_merge(
            $this->getNetServaEnvironmentVars(),
            $this->toEnvironmentArray()
        );
    }
}
