<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Contracts\ConfigManagerInterface;
use NetServa\Core\Exceptions\VHostNotFoundException;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Database VHost Configuration Service
 *
 * Manages VHost configurations using the database (FleetVhost model) as the primary source.
 * Implements the same interface as VhostConfigService for drop-in replacement.
 */
class DatabaseVhostConfigService implements ConfigManagerInterface
{
    protected VHostResolverService $resolver;

    protected NetServaEnvironmentGenerator $envGenerator;

    protected array $expectedVariables = [
        // All 53 NetServa environment variables
        'ADMIN', 'AHOST', 'AMAIL', 'ANAME', 'APASS', 'A_GID', 'A_UID',
        'BPATH', 'CIMAP', 'CSMTP', 'C_DNS', 'C_FPM', 'C_SQL', 'C_SSL', 'C_WEB',
        'DBMYS', 'DBSQL', 'DHOST', 'DNAME', 'DPASS', 'DPATH', 'DPORT', 'DTYPE', 'DUSER',
        'EPASS', 'EXMYS', 'EXSQL', 'HDOMN', 'HNAME', 'IP4_0',
        'MHOST', 'MPATH', 'OSMIR', 'OSREL', 'OSTYP',
        'SQCMD', 'SQDNS', 'TAREA', 'TCITY', 'UPASS', 'UPATH', 'UUSER',
        'U_GID', 'U_SHL', 'U_UID', 'VHOST', 'VNODE', 'VPATH', 'VUSER', 'V_PHP',
        'WPASS', 'WPATH', 'WPUSR', 'WUGID',
    ];

    public function __construct(
        VHostResolverService $resolver,
        NetServaEnvironmentGenerator $envGenerator
    ) {
        $this->resolver = $resolver;
        $this->envGenerator = $envGenerator;
    }

    /**
     * Load VHost configuration from database
     *
     * @param  string  $identifier  Format: "vnode/vhost" or just "vhost"
     * @return array All 53 NetServa environment variables
     *
     * @throws VHostNotFoundException
     */
    public function load(string $identifier): array
    {
        // Parse identifier - could be "vnode/vhost" or just "vhost"
        $parts = explode('/', $identifier);

        if (count($parts) === 2) {
            [$vnode, $vhost] = $parts;
            $context = $this->resolver->resolveVHost($vhost, $vnode);
        } else {
            $vhost = $identifier;
            $context = $this->resolver->resolveVHost($vhost);
        }

        if ($context['source'] === 'database' && isset($context['model'])) {
            /** @var FleetVhost $fleetVHost */
            $fleetVHost = $context['model'];

            if (! $fleetVHost->environment_vars) {
                throw new VHostNotFoundException("No environment variables found for {$identifier}");
            }

            return $fleetVHost->environment_vars;
        }

        throw new VHostNotFoundException("VHost not found in database: {$identifier}");
    }

    /**
     * Load VHost configuration for specific server and vhost (legacy interface)
     */
    public function loadVhostConfig(string $vnode, string $vhost): array
    {
        return $this->load("{$vnode}/{$vhost}");
    }

    /**
     * Save VHost configuration to database
     *
     * @param  string  $identifier  Format: "vnode/vhost" or just "vhost"
     * @param  array  $config  All 53 NetServa environment variables
     * @return bool Success status
     */
    public function save(string $identifier, array $config): bool
    {
        try {
            // Parse identifier
            $parts = explode('/', $identifier);

            if (count($parts) === 2) {
                [$vnode, $vhost] = $parts;
            } else {
                $vhost = $identifier;
                // Try to resolve the vnode from existing data
                try {
                    $context = $this->resolver->resolveVHost($vhost);
                    $vnode = $context['vnode'];
                } catch (Exception $e) {
                    throw new Exception("Cannot save without vnode context. Use 'vnode/vhost' format.");
                }
            }

            // Validate we have all required variables
            $validated = $this->validateAndNormalizeConfig($config);

            // Find or create the VHost record
            $fleetVHost = $this->findOrCreateVHost($vnode, $vhost, $validated);

            // Update environment variables
            $fleetVHost->environment_vars = $validated;
            $success = $fleetVHost->save();

            if ($success) {
                Log::info('VHost config saved to database', [
                    'vnode' => $vnode,
                    'vhost' => $vhost,
                    'variables_count' => count($validated),
                ]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to save VHost config to database', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if VHost configuration exists in database
     */
    public function exists(string $identifier): bool
    {
        try {
            $this->load($identifier);

            return true;
        } catch (VHostNotFoundException $e) {
            return false;
        }
    }

    /**
     * Delete VHost configuration from database
     */
    public function delete(string $identifier): bool
    {
        try {
            $parts = explode('/', $identifier);

            if (count($parts) === 2) {
                [$vnode, $vhost] = $parts;
                $context = $this->resolver->resolveVHost($vhost, $vnode);
            } else {
                $vhost = $identifier;
                $context = $this->resolver->resolveVHost($vhost);
            }

            if ($context['source'] === 'database' && isset($context['model'])) {
                /** @var FleetVhost $fleetVHost */
                $fleetVHost = $context['model'];

                return $fleetVHost->delete();
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to delete VHost config from database', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List all VHost configurations in database
     */
    public function list(): array
    {
        return FleetVhost::with(['vnode.vsite'])
            ->get()
            ->map(fn ($v) => "{$v->vnode->name}/{$v->domain}")
            ->toArray();
    }

    /**
     * Validate configuration structure
     */
    public function validate(array $config): bool
    {
        // Check that we have the essential variables
        $required = ['VHOST', 'VNODE', 'UUSER', 'WUGID', 'UPATH', 'WPATH'];

        foreach ($required as $var) {
            if (empty($config[$var])) {
                Log::warning("Missing required NetServa variable: {$var}");

                return false;
            }
        }

        return true;
    }

    /**
     * Backup configuration (not applicable for database storage)
     */
    public function backup(string $identifier): ?string
    {
        // Database has built-in backup through model timestamps and soft deletes
        // Return a logical backup reference
        return 'database_backup_'.now()->timestamp;
    }

    /**
     * Get VHosts for a specific server
     */
    public function getVhostsForServer(string $vnode): array
    {
        return FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $vnode))
            ->pluck('domain')
            ->toArray();
    }

    /**
     * Validate and normalize configuration array
     */
    protected function validateAndNormalizeConfig(array $config): array
    {
        $normalized = [];

        // Ensure all expected variables are present (fill with empty string if missing)
        foreach ($this->expectedVariables as $var) {
            $normalized[$var] = $config[$var] ?? '';
        }

        // Remove any extra variables not in the expected list
        return $normalized;
    }

    /**
     * Find or create FleetVhost record
     */
    protected function findOrCreateVHost(string $vnodeName, string $vhostDomain, array $config): FleetVhost
    {
        // Find the vnode
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            throw new Exception("VNode not found: {$vnodeName}");
        }

        // Find or create the vhost
        return FleetVhost::firstOrCreate(
            [
                'domain' => $vhostDomain,
                'vnode_id' => $vnode->id,
            ],
            [
                'status' => 'active',
                'is_active' => true,
                'description' => 'Auto-created from config import',
                'environment_vars' => $config,
            ]
        );
    }

    /**
     * Get configuration with credentials (for backward compatibility)
     */
    public function loadWithCredentials(string $vnode, string $vhost): array
    {
        $config = $this->loadVhostConfig($vnode, $vhost);

        // The environment_vars already includes all credentials
        // No need for separate .conf file lookup
        return $config;
    }

    /**
     * Initialize configuration for a vhost using NetServaEnvironmentGenerator
     *
     * This replicates the NetServa 1.0 sethost() function
     *
     * @param  FleetVhost  $vhost  The vhost to initialize
     * @param  array  $overrides  Override specific variables
     * @param  array|null  $detectedOs  OS info from remote detection (OSTYP, OSREL, OSMIR)
     * @return array All generated environment variables
     */
    public function initialize(FleetVhost $vhost, array $overrides = [], ?array $detectedOs = null): array
    {
        $vnode = $vhost->vnode;

        // Generate all 53+ environment variables (with optional OS detection)
        $envVars = $this->envGenerator->generate($vnode, $vhost->domain, $overrides, $detectedOs);

        // Store in database (both vconfs table + JSON column)
        $vhost->setEnvVars($envVars);

        return $envVars;
    }

    /**
     * Preview variables without saving (for dry-run)
     *
     * @param  FleetVnode  $vnode  The vnode
     * @param  string  $domain  The domain
     * @param  array  $overrides  Override specific variables
     * @param  array|null  $detectedOs  OS info from remote detection
     * @return array All generated environment variables (not saved)
     */
    public function previewVariables(FleetVnode $vnode, string $domain, array $overrides = [], ?array $detectedOs = null): array
    {
        return $this->envGenerator->generate($vnode, $domain, $overrides, $detectedOs);
    }

    /**
     * Initialize with minimal variables (for testing/simple configs)
     *
     * @param  FleetVhost  $vhost  The vhost to initialize
     * @param  array|null  $detectedOs  OS info from remote detection
     * @return array Minimal set of environment variables
     */
    public function initializeMinimal(FleetVhost $vhost, ?array $detectedOs = null): array
    {
        $vnode = $vhost->vnode;

        // Generate minimal set
        $envVars = $this->envGenerator->getMinimalVariables($vnode, $vhost->domain, $detectedOs);

        // Store in database
        $vhost->setEnvVars($envVars);

        return $envVars;
    }

    /**
     * Create a configuration from template (helper method)
     *
     * @deprecated Use initialize() instead which uses NetServaEnvironmentGenerator
     */
    public function createFromTemplate(string $vnode, string $vhost, array $overrides = []): array
    {
        // Find vnode
        $vnodeModel = FleetVnode::where('name', $vnode)->firstOrFail();

        // Use generator
        return $this->envGenerator->generate($vnodeModel, $vhost, $overrides);
    }
}
