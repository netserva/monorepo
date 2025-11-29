<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VhostConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'vnode',
        'vhost',
        'filepath',
        'variables',
        'migrated_at',
        'file_modified_at',
        'checksum',
    ];

    protected $casts = [
        'variables' => 'array',
        'migrated_at' => 'datetime',
        'file_modified_at' => 'datetime',
    ];

    /**
     * Get a specific environment variable value
     */
    public function getVariable(string $name, $default = null)
    {
        return $this->variables[$name] ?? $default;
    }

    /**
     * Set a specific environment variable
     */
    public function setVariable(string $name, $value): void
    {
        $variables = $this->variables ?? [];
        $variables[$name] = $value;
        $this->variables = $variables;
    }

    /**
     * Get all 54 NetServa standard variables with defaults
     */
    public function getStandardVariables(): array
    {
        return [
            'ADMIN' => $this->getVariable('ADMIN', 'sysadm'),
            'AHOST' => $this->getVariable('AHOST', $this->vhost),
            'AMAIL' => $this->getVariable('AMAIL', "admin@{$this->vhost}"),
            'ANAME' => $this->getVariable('ANAME', 'System Administrator'),
            'APASS' => $this->getVariable('APASS', ''),
            'A_GID' => $this->getVariable('A_GID', '1001'),
            'A_UID' => $this->getVariable('A_UID', '1001'),
            'BPATH' => $this->getVariable('BPATH', '/home/backups'),
            'CIMAP' => $this->getVariable('CIMAP', '/etc/dovecot'),
            'CSMTP' => $this->getVariable('CSMTP', '/etc/postfix'),
            'C_DNS' => $this->getVariable('C_DNS', '/etc/powerdns'),
            'C_FPM' => $this->getVariable('C_FPM', '/etc/php/8.4/fpm'),
            'C_SQL' => $this->getVariable('C_SQL', '/etc/mysql'),
            'C_WEB' => $this->getVariable('C_WEB', '/etc/nginx'),
            'DBMYS' => $this->getVariable('DBMYS', '/var/lib/mysql'),
            'DBSQL' => $this->getVariable('DBSQL', '/var/lib/sqlite'),
            'DHOST' => $this->getVariable('DHOST', 'localhost'),
            'DNAME' => $this->getVariable('DNAME', 'sysadm'),
            'DPASS' => $this->getVariable('DPASS', ''),
            'DPATH' => $this->getVariable('DPATH', '/var/lib/sqlite/sysadm'),
            'DPORT' => $this->getVariable('DPORT', '3306'),
            'DTYPE' => $this->getVariable('DTYPE', 'sqlite'),
            'DUSER' => $this->getVariable('DUSER', 'sysadm'),
            'EPASS' => $this->getVariable('EPASS', ''),
            'EXMYS' => $this->getVariable('EXMYS', "mysql -u{$this->getVariable('DUSER', 'sysadm')} -p{$this->getVariable('DPASS', '')} {$this->getVariable('DNAME', 'sysadm')}"),
            'EXSQL' => $this->getVariable('EXSQL', "sqlite3 {$this->getVariable('DPATH', '/var/lib/sqlite/sysadm')}/{$this->getVariable('DNAME', 'sysadm')}.db"),
            'HDOMN' => $this->getVariable('HDOMN', $this->vhost),
            'HNAME' => $this->getVariable('HNAME', $this->vnode),
            'IP4_0' => $this->getVariable('IP4_0', '192.168.1.100'),
            'MHOST' => $this->getVariable('MHOST', $this->vhost),
            'MPATH' => $this->getVariable('MPATH', "/srv/{$this->vhost}/msg"),
            'OSMIR' => $this->getVariable('OSMIR', 'http://dl-cdn.alpinelinux.org'),
            'OSREL' => $this->getVariable('OSREL', 'edge'),
            'OSTYP' => $this->getVariable('OSTYP', 'alpine'),
            'C_SSL' => $this->getVariable('C_SSL', '/etc/ssl'),
            'SQCMD' => $this->getVariable('SQCMD', "sqlite3 {$this->getVariable('DPATH', '/var/lib/sqlite/sysadm')}/{$this->getVariable('DNAME', 'sysadm')}.db"),
            'SQDNS' => $this->getVariable('SQDNS', "sqlite3 {$this->getVariable('DPATH', '/var/lib/sqlite/sysadm')}/powerdns.db"),
            'TAREA' => $this->getVariable('TAREA', 'Australia'),
            'TCITY' => $this->getVariable('TCITY', 'Sydney'),
            'UPASS' => $this->getVariable('UPASS', ''),
            'UPATH' => $this->getVariable('UPATH', "/srv/{$this->vhost}"),
            'UUSER' => $this->getVariable('UUSER', 'u1001'),
            'U_GID' => $this->getVariable('U_GID', '1001'),
            'U_SHL' => $this->getVariable('U_SHL', '/bin/bash'),
            'U_UID' => $this->getVariable('U_UID', '1001'),
            'VHOST' => $this->getVariable('VHOST', $this->vhost),
            'VNODE' => $this->getVariable('VNODE', $this->vnode),
            'VPATH' => $this->getVariable('VPATH', '/srv'),
            'VUSER' => $this->getVariable('VUSER', 'admin'),
            'V_PHP' => $this->getVariable('V_PHP', '8.4'),
            'WPASS' => $this->getVariable('WPASS', ''),
            'WPATH' => $this->getVariable('WPATH', "/srv/{$this->vhost}/web"),
            'WPUSR' => $this->getVariable('WPUSR', ''),
            'WUGID' => $this->getVariable('WUGID', 'nginx'),
        ];
    }

    /**
     * Check if configuration has all required variables
     */
    public function isComplete(): bool
    {
        $required = ['VHOST', 'VNODE', 'ADMIN', 'VPATH'];

        foreach ($required as $var) {
            if (! isset($this->variables[$var])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate shell environment script
     */
    public function toShellScript(): string
    {
        $variables = $this->getStandardVariables();
        $script = "#!/bin/bash\n";
        $script .= "# NetServa vhost configuration for {$this->vhost} on {$this->vnode}\n";
        $script .= '# Generated from database at '.now()->toDateTimeString()."\n\n";

        foreach ($variables as $name => $value) {
            $script .= "export {$name}='{$value}'\n";
        }

        return $script;
    }

    /**
     * Export configuration to original file format
     */
    public function toFileFormat(): string
    {
        $variables = $this->getStandardVariables();
        $content = "# NetServa vhost configuration for {$this->vhost} on {$this->vnode}\n";
        $content .= '# Exported from database at '.now()->toDateTimeString()."\n\n";

        foreach ($variables as $name => $value) {
            $content .= "{$name}='{$value}'\n";
        }

        return $content;
    }

    /**
     * Scopes
     */
    public function scopeByVnode($query, string $vnode)
    {
        return $query->where('vnode', $vnode);
    }

    public function scopeByVhost($query, string $vhost)
    {
        return $query->where('vhost', $vhost);
    }

    public function scopeComplete($query)
    {
        return $query->whereJsonContains('variables->VHOST', $this->vhost)
            ->whereJsonContains('variables->VNODE', $this->vnode)
            ->whereJsonContains('variables->ADMIN', function ($value) {
                return ! empty($value);
            });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\VhostConfigurationFactory::new();
    }
}
