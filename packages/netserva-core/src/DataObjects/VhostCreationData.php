<?php

namespace NetServa\Core\DataObjects;

use Illuminate\Console\Command;

/**
 * VHost Creation Data Transfer Object
 *
 * Standardizes data structure for creating virtual hosts across
 * console commands and Filament forms.
 */
readonly class VhostCreationData
{
    public function __construct(
        public string $vnode,
        public string $vhost,
        public ?string $phpVersion = '8.4',
        public bool $sslEnabled = true,
        public ?string $databaseType = 'sqlite',
        public ?string $databaseName = null,
        public ?string $adminEmail = null,
        public ?string $adminPassword = null,
        public ?string $webroot = null,
        public ?int $uid = null,
        public ?int $gid = null,
        public ?string $username = null,
    ) {}

    /**
     * Create from console command input
     */
    public static function fromConsoleInput(Command $command): self
    {
        return new self(
            vnode: $command->argument('vnode'),
            vhost: $command->argument('vhost'),
            phpVersion: $command->option('php-version') ?? '8.4',
            sslEnabled: $command->option('ssl') ?? true,
            databaseType: $command->option('db-type') ?? 'sqlite',
            databaseName: $command->option('db-name'),
            adminEmail: $command->option('admin-email'),
            adminPassword: $command->option('admin-password'),
            webroot: $command->option('webroot'),
        );
    }

    /**
     * Create from Filament form data
     */
    public static function fromFilamentForm(array $data): self
    {
        return new self(
            vnode: $data['vnode'],
            vhost: $data['vhost'],
            phpVersion: $data['php_version'] ?? '8.4',
            sslEnabled: $data['ssl_enabled'] ?? true,
            databaseType: $data['database_type'] ?? 'sqlite',
            databaseName: $data['database_name'] ?? null,
            adminEmail: $data['admin_email'] ?? null,
            adminPassword: $data['admin_password'] ?? null,
            webroot: $data['webroot'] ?? null,
            uid: $data['uid'] ?? null,
            gid: $data['gid'] ?? null,
            username: $data['username'] ?? null,
        );
    }

    /**
     * Create from VhostConfiguration model
     */
    public static function fromModel(\NetServa\Core\Models\VhostConfiguration $config): self
    {
        return new self(
            vnode: $config->vnode,
            vhost: $config->vhost,
            phpVersion: $config->getVariable('V_PHP', '8.4'),
            sslEnabled: true,
            databaseType: $config->getVariable('DTYPE', 'sqlite'),
            databaseName: $config->getVariable('DNAME'),
            adminEmail: $config->getVariable('AMAIL'),
            webroot: $config->getVariable('WPATH'),
            uid: (int) $config->getVariable('U_UID'),
            gid: (int) $config->getVariable('U_GID'),
            username: $config->getVariable('UUSER'),
        );
    }

    /**
     * Convert to array for validation or storage
     */
    public function toArray(): array
    {
        return [
            'vnode' => $this->vnode,
            'vhost' => $this->vhost,
            'php_version' => $this->phpVersion,
            'ssl_enabled' => $this->sslEnabled,
            'database_type' => $this->databaseType,
            'database_name' => $this->databaseName,
            'admin_email' => $this->adminEmail,
            'webroot' => $this->webroot,
            'uid' => $this->uid,
            'gid' => $this->gid,
            'username' => $this->username,
        ];
    }

    /**
     * Get database name (auto-generate if not provided)
     */
    public function getDatabaseName(): string
    {
        if ($this->databaseName) {
            return $this->databaseName;
        }

        // Auto-generate from domain: example.com -> example_com
        return str_replace(['.', '-'], '_', $this->vhost);
    }

    /**
     * Get username (auto-generate if not provided)
     */
    public function getUsername(): string
    {
        if ($this->username) {
            return $this->username;
        }

        // Auto-generate: u1001, u1002, etc.
        return 'u'.($this->uid ?? 1001);
    }

    /**
     * Get webroot path (auto-generate if not provided)
     */
    public function getWebroot(): string
    {
        if ($this->webroot) {
            return $this->webroot;
        }

        return "/srv/{$this->vhost}/web";
    }
}
