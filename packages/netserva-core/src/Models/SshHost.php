<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * NetServa Core SSH Host Model
 *
 * Represents a remote host that can be connected to via SSH.
 * Part of the NetServa Core foundation package.
 */
class SshHost extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\SshHostFactory::new();
    }

    protected $fillable = [
        'server_id',
        'host',
        'hostname',
        'port',
        'user',
        'identity_file',
        'proxy_command',
        'jump_host',
        'custom_options',
        'description',
        'is_active',
        'last_tested_at',
        'is_reachable',
        'last_error',
    ];

    protected $casts = [
        'port' => 'integer',
        'custom_options' => 'array',
        'is_active' => 'boolean',
        'is_reachable' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $attributes = [
        'port' => 22,
        'user' => 'root',
        'is_active' => true,
    ];

    /**
     * Get the server this SSH host belongs to
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get SSH keys for this host
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(SshKey::class);
    }

    /**
     * Get SSH connections for this host
     */
    public function sshConnections(): HasMany
    {
        return $this->hasMany(SshConnection::class);
    }

    /**
     * Alias for sshConnections for backward compatibility
     */
    public function connections(): HasMany
    {
        return $this->sshConnections();
    }

    /**
     * Scope to get only active hosts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get hosts ordered for display
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('host');
    }

    /**
     * Generate SSH config format for this host
     */
    public function toSshConfig(): string
    {
        if (! $this->is_active) {
            return '';
        }

        $config = [];
        $config[] = "Host {$this->host}";
        $config[] = "  Hostname {$this->hostname}";

        if ($this->port !== 22) {
            $config[] = "  Port {$this->port}";
        }

        if ($this->user !== 'root') {
            $config[] = "  User {$this->user}";
        }

        if ($this->identity_file) {
            $config[] = "  IdentityFile {$this->identity_file}";
        }

        if ($this->proxy_command) {
            $config[] = "  ProxyCommand {$this->proxy_command}";
        }

        if ($this->jump_host) {
            $config[] = "  ProxyJump {$this->jump_host}";
        }

        // Add custom options
        if ($this->custom_options && is_array($this->custom_options)) {
            foreach ($this->custom_options as $option => $value) {
                $config[] = "  {$option} {$value}";
            }
        }

        return implode("\n", $config);
    }

    /**
     * Get the SSH connection string for this host
     */
    public function getConnectionString(): string
    {
        $connection = $this->user.'@'.$this->hostname;
        if ($this->port !== 22) {
            $connection .= ':'.$this->port;
        }

        return $connection;
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->host;
    }

    /**
     * Check if this host can be reached via SSH
     */
    public function isReachable(): bool
    {
        $command = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s -p %d true 2>/dev/null',
            $this->user,
            $this->hostname,
            $this->port
        );

        $result = Process::run($command);

        return $result->successful();
    }

    /**
     * Generate runtime SSH configuration for all active hosts
     */
    public static function generateRuntimeConfig(): string
    {
        $config = [];
        $config[] = '# NetServa SSH Host Configurations - Generated '.now()->toDateTimeString();
        $config[] = '# Managed by Laravel/Filament - Do not edit directly';
        $config[] = '';

        $hosts = self::active()->ordered()->get();

        foreach ($hosts as $host) {
            // Add host description if available
            if ($host->description) {
                $config[] = "# {$host->description}";
            }

            $config[] = $host->toSshConfig();
            $config[] = '';
        }

        return implode("\n", $config);
    }

    /**
     * Regenerate runtime SSH configuration file
     */
    public static function regenerateRuntimeConfig()
    {
        // Get runtime directory - using NetServa environment variables
        // Get NSRUN directory, defaulting to HOME/.ns/run if not set
        $nsrunDir = env('NSRUN');
        if (! $nsrunDir) {
            $home = env('HOME', '/home/'.get_current_user());
            $nsrunDir = $home.'/.ns/run';
        }
        $nsrunDir = rtrim($nsrunDir, '/');
        $sshDir = "{$nsrunDir}/ssh";

        // Create directory if it doesn't exist
        if (! File::exists($sshDir)) {
            File::makeDirectory($sshDir, 0700, true);
        }

        // Generate and write runtime config
        $runtimeConfig = self::generateRuntimeConfig();
        $runtimeConfigPath = "{$sshDir}/runtime_config";

        File::put($runtimeConfigPath, $runtimeConfig);
        chmod($runtimeConfigPath, 0600);

        // Also create the mux directory for ControlPath
        $muxDir = "{$sshDir}/mux";
        if (! File::exists($muxDir)) {
            File::makeDirectory($muxDir, 0700, true);
        }
    }

    /**
     * Import existing SSH configs from filesystem
     */
    public static function importFromFilesystem()
    {
        $homeDir = env('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['dir'] : '/home/'.get_current_user());
        $nsshDir = rtrim(env('NSSSH', '~/.ns/ssh'), '/').'/hosts';
        $nsshDir = str_replace('~', $homeDir, $nsshDir);

        if (! File::exists($nsshDir)) {
            return [];
        }

        $imported = [];
        $files = File::files($nsshDir);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip the config file and other non-host files
            if (in_array($filename, ['config', '.', '..']) || str_starts_with($filename, '.')) {
                continue;
            }

            $content = File::get($file->getPathname());
            $hostConfig = self::parseSSHConfig($content, $filename);

            if ($hostConfig) {
                // Check if host already exists
                $existing = self::where('host', $hostConfig['host'])->first();

                if (! $existing) {
                    $host = self::create($hostConfig);
                    $imported[] = $host;
                }
            }
        }

        return $imported;
    }

    /**
     * Parse SSH config content into host data
     */
    protected static function parseSSHConfig(string $content, string $filename): ?array
    {
        $lines = explode("\n", $content);
        $hostData = [
            'host' => $filename,
            'hostname' => '',
            'port' => 22,
            'user' => 'root',
            'identity_file' => null,
            'proxy_command' => null,
            'jump_host' => null,
            'custom_options' => [],
            'is_active' => true,
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $directive = $parts[0];
            $value = $parts[1];

            switch (strtolower($directive)) {
                case 'host':
                    $hostData['host'] = $value;
                    break;
                case 'hostname':
                    $hostData['hostname'] = $value;
                    break;
                case 'port':
                    $hostData['port'] = (int) $value;
                    break;
                case 'user':
                    $hostData['user'] = $value;
                    break;
                case 'identityfile':
                    $hostData['identity_file'] = $value;
                    break;
                case 'proxycommand':
                    $hostData['proxy_command'] = $value;
                    break;
                case 'proxyjump':
                    $hostData['jump_host'] = $value;
                    break;
                default:
                    $hostData['custom_options'][$directive] = $value;
                    break;
            }
        }

        // Must have hostname to be valid
        return $hostData['hostname'] ? $hostData : null;
    }

    /**
     * Regenerate configs after host changes
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            self::regenerateRuntimeConfig();
        });

        static::deleted(function ($model) {
            self::regenerateRuntimeConfig();
        });
    }
}
