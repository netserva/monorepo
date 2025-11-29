<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVenue;
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
 *   addpw                              # Interactive mode (prompts for all)
 *   addpw vnode mgo cloudflare         # Add Cloudflare API key for mgo vnode
 *   addpw vhost example.com dovecot    # Add Dovecot password for vhost
 *
 * NetServa 3.0 Security Architecture:
 * - ALL credentials stored on workstation only (encrypted at rest)
 * - Polymorphic ownership: venue/vsite/vnode/vhost
 * - Hierarchical inheritance resolution
 * - Supports: VMAIL, APKEY, DBPWD, SSLKY, OAUTH
 */
class AddpwCommand extends Command
{
    protected $signature = 'addpw
                            {name? : VNode name, domain, or vnode+domain}
                            {service_or_domain? : Service provider OR domain (if first arg is vnode)}
                            {pserv? : Service provider (if first two args are vnode+domain)}
                            {--ptype= : Password type (VMAIL, APKEY, DBPWD, SSLKY, OAUTH)}
                            {--pname= : Identifier name (email, key name, username, default)}
                            {--pdata= : Secret data (password, API key, token)}
                            {--pmeta= : Metadata JSON}
                            {--pnote= : Admin notes}
                            {--pexpd= : Expiration date (YYYY-MM-DD)}';

    protected $description = 'Add credential to unified vault (VMAIL, APKEY, DBPWD, SSLKY, OAUTH)';

    public function handle(): int
    {
        try {
            // Get or prompt for owner
            $ownerType = $this->argument('owner_type') ?: select(
                label: 'Owner type',
                options: ['venue', 'vsite', 'vnode', 'vhost'],
                default: 'vnode',
                hint: 'Where to store this credential'
            );

            $ownerName = $this->argument('owner_name') ?: text(
                label: 'Owner name',
                placeholder: match ($ownerType) {
                    'venue' => 'venue-name',
                    'vsite' => 'vsite-name',
                    'vnode' => 'vnode-name (e.g., mgo, syd, cachyos)',
                    'vhost' => 'domain.com',
                },
                required: true,
                hint: 'The entity that owns this credential'
            );

            // Find owner model
            $owner = $this->findOwner($ownerType, $ownerName);
            if (! $owner) {
                error("{$ownerType} not found: {$ownerName}");

                return Command::FAILURE;
            }

            // Get or prompt for service
            $pserv = $this->argument('pserv') ?: select(
                label: 'Service provider',
                options: [
                    'cloudflare' => 'Cloudflare (DNS, CDN)',
                    'binarylane' => 'BinaryLane (Cloud)',
                    'proxmox' => 'Proxmox (Virtualization)',
                    'dovecot' => 'Dovecot (Mail)',
                    'mysql' => 'MySQL/MariaDB (Database)',
                    'postgresql' => 'PostgreSQL (Database)',
                    'redis' => 'Redis (Cache)',
                    'custom' => 'Custom service',
                ],
                hint: 'The service this credential is for'
            );

            if ($pserv === 'custom') {
                $pserv = text(
                    label: 'Custom service name',
                    placeholder: 'service-name',
                    required: true,
                    hint: 'Lowercase, no spaces'
                );
            }

            // Get or prompt for password type
            $ptype = $this->option('ptype') ?: select(
                label: 'Credential type',
                options: [
                    'APKEY' => 'API Key (default)',
                    'VMAIL' => 'Email Password',
                    'DBPWD' => 'Database Password',
                    'SSLKY' => 'SSL Private Key',
                    'OAUTH' => 'OAuth Token',
                ],
                default: 'APKEY',
                hint: 'Type of credential'
            );

            // Get or prompt for identifier name
            $pname = $this->option('pname') ?: text(
                label: 'Identifier name',
                placeholder: match ($ptype) {
                    'VMAIL' => 'user@domain.com',
                    'APKEY' => 'api-key-name or default',
                    'DBPWD' => 'database-name or username',
                    'SSLKY' => 'domain.com',
                    'OAUTH' => 'oauth-provider',
                    default => 'default',
                },
                default: 'default',
                required: true,
                hint: 'Unique identifier for this credential'
            );

            // Check if credential already exists
            $existing = VPass::byOwner($owner)
                ->where('pserv', $pserv)
                ->where('pname', $pname)
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

            // Get or prompt for secret data
            $pdata = $this->option('pdata') ?: password(
                label: match ($ptype) {
                    'VMAIL' => 'Email password',
                    'APKEY' => 'API key',
                    'DBPWD' => 'Database password',
                    'SSLKY' => 'SSL private key',
                    'OAUTH' => 'OAuth token',
                    default => 'Secret',
                },
                required: true,
                hint: 'Will be encrypted at rest with APP_KEY'
            );

            // Optional metadata
            $pmeta = null;
            if ($this->option('pmeta')) {
                $pmeta = json_decode($this->option('pmeta'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error('Invalid JSON in --pmeta option');

                    return Command::FAILURE;
                }
            }

            // Optional notes
            $pnote = $this->option('pnote') ?: text(
                label: 'Admin notes (optional)',
                placeholder: 'Created for production access',
                hint: 'Press Enter to skip'
            );

            // Optional expiration
            $pexpd = null;
            if ($this->option('pexpd')) {
                $pexpd = $this->option('pexpd');
            } elseif (confirm(
                label: 'Set expiration date?',
                default: false,
                hint: 'Useful for rotating credentials'
            )) {
                $pexpd = text(
                    label: 'Expiration date',
                    placeholder: 'YYYY-MM-DD',
                    validate: fn ($value) => strtotime($value) ? null : 'Invalid date format'
                );
            }

            // Create or update credential
            $credential = VPass::findOrCreate(
                owner: $owner,
                pserv: $pserv,
                pname: $pname,
                ptype: $ptype,
                pdata: $pdata,
                pmeta: $pmeta
            );

            // Update optional fields
            if ($pnote) {
                $credential->pnote = $pnote;
            }
            if ($pexpd) {
                $credential->pexpd = $pexpd;
            }
            $credential->save();

            // Success message
            info('Credential saved successfully');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Owner', "{$credential->owner_type_display}: {$ownerName}"],
                    ['Service', $pserv],
                    ['Type', $credential->type_display],
                    ['Name', $pname],
                    ['Status', $credential->pstat ? '✓ Active' : '✗ Disabled'],
                    ['Created', $credential->created_at->format('Y-m-d H:i:s')],
                    ['Rotated', $credential->pdate?->format('Y-m-d H:i:s') ?? 'Never'],
                    ['Expires', $credential->pexpd?->format('Y-m-d H:i:s') ?? 'Never'],
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
     * Find owner model by type and name
     */
    private function findOwner(string $type, string $name): ?object
    {
        return match ($type) {
            'venue' => FleetVenue::where('name', $name)->first(),
            'vsite' => FleetVsite::where('name', $name)->first(),
            'vnode' => FleetVnode::where('name', $name)->first(),
            'vhost' => FleetVhost::where('fqdn', $name)->first(),
            default => null,
        };
    }
}
