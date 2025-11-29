<?php

namespace NetServa\Core\ValueObjects;

/**
 * VHost Paths Value Object
 *
 * Type-safe container for all NetServa VHost file system paths.
 */
readonly class VhostPaths
{
    public function __construct(
        public string $vhost,
        public string $vpath,
        public string $upath,
        public string $wpath,
        public string $mpath,
        public string $bpath,
        public string $dbpath,
        public string $sslPath,
        public string $phpFpmPath,
        public string $nginxPath,
        public string $postfixPath,
        public string $dovecotPath,
        public string $dnsPath,
        public string $mysqlPath
    ) {}

    /**
     * Get all paths as associative array
     */
    public function toArray(): array
    {
        return [
            'vhost' => $this->vhost,
            'vpath' => $this->vpath,
            'upath' => $this->upath,
            'wpath' => $this->wpath,
            'mpath' => $this->mpath,
            'bpath' => $this->bpath,
            'dbpath' => $this->dbpath,
            'ssl_path' => $this->sslPath,
            'php_fpm_path' => $this->phpFpmPath,
            'nginx_path' => $this->nginxPath,
            'postfix_path' => $this->postfixPath,
            'dovecot_path' => $this->dovecotPath,
            'dns_path' => $this->dnsPath,
            'mysql_path' => $this->mysqlPath,
        ];
    }

    /**
     * Get user-specific paths
     */
    public function getUserPaths(): array
    {
        return [
            'user_home' => $this->upath,
            'web_root' => $this->wpath,
            'mail_path' => $this->mpath,
        ];
    }

    /**
     * Get service configuration paths
     */
    public function getServicePaths(): array
    {
        return [
            'php_fpm' => $this->phpFpmPath,
            'nginx' => $this->nginxPath,
            'postfix' => $this->postfixPath,
            'dovecot' => $this->dovecotPath,
            'dns' => $this->dnsPath,
            'mysql' => $this->mysqlPath,
            'ssl' => $this->sslPath,
        ];
    }
}
