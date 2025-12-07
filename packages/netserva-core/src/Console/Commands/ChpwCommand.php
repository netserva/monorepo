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
use function Laravel\Prompts\warning;

/**
 * Change Password Command - NetServa 3.0
 *
 * CRUD: Update - Modify existing credential in unified vault
 *
 * Usage:
 *   chpw vnode mgo cloudflare default     # Update Cloudflare API key for mgo
 *   chpw vhost example.com dovecot admin@example.com  # Update email password
 *   chpw --import < backup.txt            # Import from shpw --export backup
 *   chpw --import=/path/to/backup.txt     # Import from file path
 *
 * NetServa 3.0 Security Architecture:
 * - Updates encrypted credentials on workstation vault
 * - Supports password rotation tracking
 * - Import/export for disaster recovery
 */
class ChpwCommand extends Command
{
    protected $signature = 'chpw
                            {owner_type? : Owner type (venue/vsite/vnode/vhost)}
                            {owner_name? : Owner name}
                            {pserv? : Service provider}
                            {pname? : Identifier name}
                            {--new-secret= : New password/API key/token}
                            {--disable : Disable credential without deleting}
                            {--enable : Enable disabled credential}
                            {--rotate : Mark as rotated (update pdate)}
                            {--expiry= : Set new expiration date (YYYY-MM-DD)}
                            {--import=? : Import from shpw --export (file path or stdin)}';

    protected $description = 'Update credentials in unified vault (UPDATE)';

    public function handle(): int
    {
        // Handle import mode
        if ($this->option('import') !== null) {
            return $this->importCredentials();
        }

        try {
            // Get credential to update
            $ownerType = $this->argument('owner_type');
            $ownerName = $this->argument('owner_name');
            $pserv = $this->argument('pserv');
            $pname = $this->argument('pname');

            if (! $ownerType || ! $ownerName || ! $pserv || ! $pname) {
                error('All arguments required: owner_type owner_name pserv pname');
                $this->line('Example: chpw vnode mgo cloudflare default');

                return Command::FAILURE;
            }

            // Find owner
            $owner = $this->findOwner($ownerType, $ownerName);
            if (! $owner) {
                error("{$ownerType} not found: {$ownerName}");

                return Command::FAILURE;
            }

            // Find credential
            $credential = VPass::byOwner($owner)
                ->where('pserv', $pserv)
                ->where('pname', $pname)
                ->first();

            if (! $credential) {
                error("Credential not found: {$pserv}/{$pname} for {$ownerType} {$ownerName}");
                warning("Use 'addpw' to create it first");

                return Command::FAILURE;
            }

            // Display current credential info
            info("Updating credential: {$credential->type_display} for {$pserv}");
            $this->line('');
            $this->line("Owner: {$credential->owner_type_display} - {$ownerName}");
            $this->line("Service: {$pserv}");
            $this->line("Name: {$pname}");
            $this->line('Status: '.($credential->pstat ? '✓ Active' : '✗ Disabled'));
            $this->line('');

            $updated = false;

            // Update secret if provided
            if ($newSecret = $this->option('new-secret')) {
                if (confirm(
                    label: 'Update secret data?',
                    default: true,
                    hint: 'This will replace the current password/API key'
                )) {
                    $credential->pdata = $newSecret;
                    $credential->pdate = now(); // Mark as rotated
                    $updated = true;
                    info('Secret updated');
                }
            }

            // Disable/enable
            if ($this->option('disable')) {
                $credential->disable();
                $updated = true;
                warning('Credential disabled');
            }

            if ($this->option('enable')) {
                $credential->enable();
                $updated = true;
                info('Credential enabled');
            }

            // Mark as rotated
            if ($this->option('rotate')) {
                $credential->markRotated();
                $updated = true;
                info('Marked as rotated');
            }

            // Update expiration
            if ($expiry = $this->option('expiry')) {
                if (! strtotime($expiry)) {
                    error('Invalid date format. Use YYYY-MM-DD');

                    return Command::FAILURE;
                }
                $credential->pexpd = $expiry;
                $updated = true;
                info("Expiration set to {$expiry}");
            }

            if (! $updated) {
                warning('No changes made. Use --new-secret, --disable, --enable, --rotate, or --expiry');

                return Command::SUCCESS;
            }

            $credential->save();

            // Success summary
            $this->line('');
            info('Credential updated successfully');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Service', $credential->pserv],
                    ['Type', $credential->type_display],
                    ['Name', $credential->pname],
                    ['Status', $credential->pstat ? '✓ Active' : '✗ Disabled'],
                    ['Last Rotated', $credential->pdate?->format('Y-m-d H:i:s') ?? 'Never'],
                    ['Expires', $credential->pexpd?->format('Y-m-d H:i:s') ?? 'Never'],
                    ['Updated', $credential->updated_at->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Failed to update credential: '.$e->getMessage());
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

    /**
     * Find owner by fully qualified class name and identifier
     */
    private function findOwnerByClass(string $ownerType, string $ownerName): ?object
    {
        // Extract short type from class name (FleetVenue -> venue)
        $shortType = match (true) {
            str_contains($ownerType, 'FleetVenue') => 'venue',
            str_contains($ownerType, 'FleetVsite') => 'vsite',
            str_contains($ownerType, 'FleetVnode') => 'vnode',
            str_contains($ownerType, 'FleetVhost') => 'vhost',
            default => strtolower(class_basename($ownerType)),
        };

        return $this->findOwner($shortType, $ownerName);
    }

    /**
     * Import credentials from shpw --csv format
     * Format: owner_type,owner_name,ptype,pserv,pname,pdata,pmeta_json
     */
    private function importCredentials(): int
    {
        $filePath = $this->option('import');

        // Read from file or stdin
        if ($filePath && $filePath !== true && file_exists($filePath)) {
            $handle = fopen($filePath, 'r');
        } elseif (! posix_isatty(STDIN)) {
            $handle = STDIN;
        } else {
            error('No input file or stdin provided');
            $this->line('Usage: php artisan chpw --import < backup.csv');
            $this->line('   or: php artisan chpw --import=/path/to/backup.csv');

            return Command::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $lineNum = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            // Skip header row
            if ($lineNum === 1 && ($row[0] ?? '') === 'owner_type') {
                continue;
            }

            // Skip empty rows
            if (empty($row) || empty($row[0])) {
                continue;
            }

            if (count($row) < 6) {
                warning("Line {$lineNum}: Invalid format (expected 6-7 fields)");
                $errors++;

                continue;
            }

            [$ownerType, $ownerName, $ptype, $pserv, $pname, $pdata] = $row;
            $pmeta = isset($row[6]) && $row[6] ? json_decode($row[6], true) : null;

            // Find owner
            $owner = $this->findOwnerByClass($ownerType, $ownerName);
            if (! $owner) {
                warning("Line {$lineNum}: Owner not found: {$ownerType} '{$ownerName}' - skipped");
                $skipped++;

                continue;
            }

            try {
                VPass::findOrCreate(
                    owner: $owner,
                    pserv: $pserv,
                    pname: $pname,
                    ptype: $ptype,
                    pdata: $pdata,
                    pmeta: $pmeta
                );
                $imported++;
                $this->line("Imported: {$pserv}/{$pname} for {$ownerName}");
            } catch (Exception $e) {
                warning("Line {$lineNum}: {$e->getMessage()}");
                $errors++;
            }
        }

        if ($handle !== STDIN) {
            fclose($handle);
        }

        $this->line('');
        info("Import complete: {$imported} imported, {$skipped} skipped, {$errors} errors");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
