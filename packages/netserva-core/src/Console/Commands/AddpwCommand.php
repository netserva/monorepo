<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Add Password/Credential Command - NetServa 3.0
 *
 * CRUD: Create - Add new credential to unified vault
 *
 * Usage:
 *   addpw                           # Interactive mode
 *   addpw mrn                       # Add to vnode (no dots = vnode)
 *   addpw example.com               # Add to vhost (has dots = domain)
 *   addpw --service=cloudflare      # Pre-set service type
 *
 * Smart Resolution:
 *   - No dots = VNode (mrn, gw, ns1gc)
 *   - Has dots = VHost (example.com)
 *
 * NetServa 3.0 Security Architecture:
 * - ALL credentials stored on workstation only (encrypted at rest via APP_KEY)
 * - Linked to vsite/vnode/vhost via FK relationships
 */
class AddpwCommand extends Command
{
    protected $signature = 'addpw
                            {name? : VNode name (no dots) or domain (has dots)}
                            {--service= : Service type (cloudflare, mysql, dovecot, etc.)}
                            {--name= : Credential name/identifier}
                            {--username= : Username (optional)}
                            {--password= : Secret data (password, API key)}
                            {--url= : URL endpoint (optional)}
                            {--port= : Port number (optional)}
                            {--notes= : Admin notes (optional)}
                            {--import= : Import from CSV file (from shpw --csv backup)}';

    protected $description = 'Add credential to unified vault (CREATE)';

    public function handle(): int
    {
        // Handle import mode
        if ($importFile = $this->option('import')) {
            return $this->importFromCsv($importFile);
        }

        try {
            // Smart owner resolution
            $ownerName = $this->argument('name') ?: text(
                label: 'Owner (vnode or domain)',
                placeholder: 'mrn or example.com',
                required: true,
                hint: 'No dots = VNode, has dots = VHost'
            );

            $owner = $this->resolveOwner($ownerName);
            if (! $owner) {
                return Command::FAILURE;
            }

            $ownerType = $this->getOwnerType($owner);
            $ownerDisplay = $this->getOwnerDisplay($owner);

            // Get or prompt for service
            $service = $this->option('service') ?: select(
                label: 'Service type',
                options: [
                    'cloudflare' => 'Cloudflare (DNS, CDN)',
                    'synergywholesale' => 'SynergyWholesale (Domain Registrar)',
                    'binarylane' => 'BinaryLane (Cloud)',
                    'mysql' => 'MySQL/MariaDB (Database)',
                    'postgresql' => 'PostgreSQL (Database)',
                    'dovecot' => 'Dovecot (Mail)',
                    'roundcube' => 'Roundcube (Webmail)',
                    'wordpress' => 'WordPress (CMS)',
                    'ssh' => 'SSH (Remote Access)',
                    'api' => 'API (Generic)',
                    'custom' => 'Custom service',
                ],
                hint: 'The service this credential is for'
            );

            if ($service === 'custom') {
                $service = text(
                    label: 'Custom service name',
                    placeholder: 'service-name',
                    required: true,
                    hint: 'Lowercase, no spaces'
                );
            }

            // Get credential name/identifier
            $name = $this->option('name') ?: text(
                label: 'Credential name',
                placeholder: match ($service) {
                    'dovecot' => 'user@domain.com',
                    'mysql', 'postgresql' => 'database_user',
                    'cloudflare', 'synergywholesale', 'binarylane' => 'api_key or default',
                    'wordpress' => 'admin or domain.com',
                    default => 'default',
                },
                default: 'default',
                required: true,
                hint: 'Unique identifier for this credential'
            );

            // Check for existing credential
            $ownerKey = $this->getOwnerKey($owner);
            $existing = VPass::where($ownerKey, $owner->id)
                ->where('service', $service)
                ->where('name', $name)
                ->first();

            if ($existing) {
                if (! confirm(
                    label: 'Credential already exists. Overwrite?',
                    default: false,
                    hint: 'This will update the existing credential'
                )) {
                    warning('Operation cancelled');

                    return Command::SUCCESS;
                }
            }

            // Get username (optional)
            $username = $this->option('username') ?: text(
                label: 'Username (optional)',
                placeholder: 'admin@example.com',
                hint: 'Press Enter to skip'
            );

            // Get password/secret
            $secret = $this->option('password') ?: password(
                label: match ($service) {
                    'dovecot' => 'Email password',
                    'mysql', 'postgresql' => 'Database password',
                    'cloudflare', 'synergywholesale', 'binarylane', 'api' => 'API key',
                    default => 'Password/Secret',
                },
                required: true,
                hint: 'Will be encrypted at rest with APP_KEY'
            );

            // Get optional URL
            $url = $this->option('url') ?: text(
                label: 'URL endpoint (optional)',
                placeholder: 'https://api.example.com',
                hint: 'Press Enter to skip'
            );

            // Get optional port
            $port = $this->option('port');
            if (! $port) {
                $portInput = text(
                    label: 'Port number (optional)',
                    placeholder: '3306, 443, etc.',
                    hint: 'Press Enter to skip'
                );
                $port = $portInput ? (int) $portInput : null;
            }

            // Get optional notes
            $notes = $this->option('notes') ?: text(
                label: 'Admin notes (optional)',
                placeholder: 'Created for production access',
                hint: 'Press Enter to skip'
            );

            // Create or update credential using VPass::store()
            $credential = VPass::store(
                owner: $owner,
                service: $service,
                name: $name,
                password: $secret,
                username: $username ?: null,
                url: $url ?: null,
                port: $port,
                notes: $notes ?: null
            );

            // Success message
            info('Credential saved successfully');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Owner', "{$ownerType}: {$ownerDisplay}"],
                    ['Service', $credential->service_display],
                    ['Name', $credential->name],
                    ['Username', $credential->username ?: '-'],
                    ['URL', $credential->url ?: '-'],
                    ['Port', $credential->port ?: '-'],
                    ['Created', $credential->created_at->format('Y-m-d H:i:s')],
                ]
            );

            if ($existing) {
                warning('Updated existing credential');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Failed to add credential: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Smart owner resolution: dots = domain = VHost, no dots = VNode
     */
    private function resolveOwner(string $name): ?object
    {
        if (str_contains($name, '.')) {
            // Has dots = domain = VHost
            $vhost = FleetVhost::where('domain', $name)->first();
            if (! $vhost) {
                error("VHost not found: {$name}");

                return null;
            }

            return $vhost;
        }

        // No dots = VNode
        $vnode = FleetVnode::where('name', $name)->first();
        if (! $vnode) {
            error("VNode not found: {$name}");

            return null;
        }

        return $vnode;
    }

    /**
     * Get owner type display name
     */
    private function getOwnerType(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => 'VHost',
            $owner instanceof FleetVnode => 'VNode',
            $owner instanceof FleetVsite => 'VSite',
            default => 'Unknown',
        };
    }

    /**
     * Get owner display name
     */
    private function getOwnerDisplay(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => $owner->domain,
            $owner instanceof FleetVnode => $owner->name,
            $owner instanceof FleetVsite => $owner->name,
            default => $owner->id,
        };
    }

    /**
     * Get FK column name for owner
     */
    private function getOwnerKey(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => 'fleet_vhost_id',
            $owner instanceof FleetVnode => 'fleet_vnode_id',
            $owner instanceof FleetVsite => 'fleet_vsite_id',
            default => throw new Exception('Invalid owner type'),
        };
    }

    /**
     * Import credentials from CSV backup file
     *
     * CSV format (from shpw --csv):
     * name, service, username, password, url, port, notes, vsite, vnode, vhost
     */
    private function importFromCsv(string $filePath): int
    {
        // Expand ~ to home directory
        if (str_starts_with($filePath, '~/')) {
            $filePath = ($_SERVER['HOME'] ?? getenv('HOME')).substr($filePath, 1);
        }

        if (! file_exists($filePath)) {
            error("File not found: {$filePath}");

            return Command::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            error("Cannot open file: {$filePath}");

            return Command::FAILURE;
        }

        // Read and validate header
        $header = fgetcsv($handle);
        $expectedHeader = ['name', 'service', 'username', 'password', 'url', 'port', 'notes', 'vsite', 'vnode', 'vhost'];

        if ($header !== $expectedHeader) {
            error('Invalid CSV format. Expected header: '.implode(', ', $expectedHeader));
            fclose($handle);

            return Command::FAILURE;
        }

        info("Importing credentials from: {$filePath}");
        $this->line('');

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $lineNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            if (count($row) !== 10) {
                warning("Line {$lineNum}: Invalid column count, skipping");
                $errors++;

                continue;
            }

            [$name, $service, $username, $password, $url, $port, $notes, $vsiteName, $vnodeName, $vhostDomain] = $row;

            // Resolve owner - priority: vhost > vnode > vsite
            $owner = null;

            if ($vhostDomain) {
                $owner = FleetVhost::where('domain', $vhostDomain)->first();
                if (! $owner) {
                    warning("Line {$lineNum}: VHost '{$vhostDomain}' not found, skipping");
                    $skipped++;

                    continue;
                }
            } elseif ($vnodeName) {
                $owner = FleetVnode::where('name', $vnodeName)->first();
                if (! $owner) {
                    warning("Line {$lineNum}: VNode '{$vnodeName}' not found, skipping");
                    $skipped++;

                    continue;
                }
            } elseif ($vsiteName) {
                $owner = FleetVsite::where('name', $vsiteName)->first();
                if (! $owner) {
                    warning("Line {$lineNum}: VSite '{$vsiteName}' not found, skipping");
                    $skipped++;

                    continue;
                }
            }

            try {
                if ($owner) {
                    // Credential with owner - use VPass::store()
                    VPass::store(
                        owner: $owner,
                        service: $service,
                        name: $name,
                        password: $password,
                        username: $username ?: null,
                        url: $url ?: null,
                        port: $port ? (int) $port : null,
                        notes: $notes ?: null
                    );
                    $ownerDisplay = $vhostDomain ?: $vnodeName ?: $vsiteName;
                } else {
                    // Global credential (no owner) - create directly
                    VPass::updateOrCreate(
                        ['service' => $service, 'name' => $name],
                        [
                            'password' => $password,
                            'username' => $username ?: null,
                            'url' => $url ?: null,
                            'port' => $port ? (int) $port : null,
                            'notes' => $notes ?: null,
                        ]
                    );
                    $ownerDisplay = 'Global';
                }

                $this->line("  ✓ {$service}/{$name} → {$ownerDisplay}");
                $imported++;

            } catch (Exception $e) {
                warning("Line {$lineNum}: Import failed - {$e->getMessage()}");
                $errors++;
            }
        }

        fclose($handle);

        $this->line('');
        info("Import complete: {$imported} imported, {$skipped} skipped, {$errors} errors");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
