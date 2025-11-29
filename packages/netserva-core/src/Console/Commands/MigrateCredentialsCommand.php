<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\MailCredential;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVhost;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Migrate Credentials Command - NetServa 3.0
 *
 * Migrate legacy mail_credentials to unified VPass vault
 *
 * Usage:
 *   migrate:credentials              # Migrate all mail_credentials
 *   migrate:credentials --dry-run    # Preview migration
 *   migrate:credentials --vnode=markc # Migrate only markc vnode credentials
 *
 * Security:
 * - Preserves encrypted cleartext passwords
 * - Maintains rotation timestamps
 * - Maps to polymorphic VPass structure
 */
class MigrateCredentialsCommand extends Command
{
    protected $signature = 'migrate:credentials
                            {--vnode= : Migrate only credentials for specific vnode}
                            {--dry-run : Preview migration without making changes}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Migrate mail_credentials to unified VPass vault';

    public function handle(): int
    {
        try {
            // Build query
            $query = MailCredential::with('vhost.vnode');

            if ($vnode = $this->option('vnode')) {
                $query->whereHas('vhost.vnode', function ($q) use ($vnode) {
                    $q->where('name', $vnode);
                });
            }

            $credentials = $query->get();

            if ($credentials->isEmpty()) {
                $context = $this->option('vnode') ? "for vnode {$this->option('vnode')}" : '';
                warning("No mail_credentials found to migrate {$context}");

                return Command::SUCCESS;
            }

            // Display summary
            info("Found {$credentials->count()} mail credentials to migrate");
            $this->line('');

            $grouped = $credentials->groupBy(fn ($c) => $c->vhost->vnode->name ?? 'unknown');
            foreach ($grouped as $vnodeName => $creds) {
                $this->line("  {$vnodeName}: {$creds->count()} credentials");
            }
            $this->line('');

            // Dry run preview
            if ($this->option('dry-run')) {
                info('DRY RUN: Preview of migration');
                $this->previewMigration($credentials);

                return Command::SUCCESS;
            }

            // Confirm migration
            if (! $this->option('force')) {
                if (! confirm(
                    label: "Migrate {$credentials->count()} credentials to VPass?",
                    default: true,
                    hint: 'Original mail_credentials will be preserved'
                )) {
                    warning('Migration cancelled');

                    return Command::SUCCESS;
                }
            }

            // Perform migration
            $migrated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($credentials as $mailCred) {
                try {
                    $result = $this->migrateCredential($mailCred);

                    if ($result['migrated']) {
                        $migrated++;
                        $this->line(" Migrated: {$mailCred->email}");
                    } else {
                        $skipped++;
                        $this->line("˜ Skipped: {$mailCred->email} ({$result['reason']})");
                    }
                } catch (Exception $e) {
                    $errors[] = "{$mailCred->email}: {$e->getMessage()}";
                    $this->line(" Error: {$mailCred->email}");
                }
            }

            // Summary
            $this->line('');
            info('Migration complete');
            $this->line("Migrated: {$migrated}");
            $this->line("Skipped: {$skipped}");
            $this->line('Errors: '.count($errors));

            if (! empty($errors)) {
                $this->line('');
                warning('Errors encountered:');
                foreach ($errors as $error) {
                    $this->line("  - {$error}");
                }
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Migration failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Preview migration (dry run)
     */
    private function previewMigration($credentials): void
    {
        $this->table(
            ['Email', 'VHost', 'VNode', 'Status', 'Created', 'Last Rotated'],
            $credentials->map(function ($cred) {
                return [
                    $cred->email,
                    $cred->vhost->domain ?? $cred->vhost->fqdn ?? 'N/A',
                    $cred->vhost->vnode->name ?? 'N/A',
                    $cred->is_active ? ' Active' : ' Disabled',
                    $cred->created_at->format('Y-m-d'),
                    $cred->last_rotated_at?->format('Y-m-d') ?? 'Never',
                ];
            })->toArray()
        );
    }

    /**
     * Migrate single mail credential to VPass
     */
    private function migrateCredential(MailCredential $mailCred): array
    {
        $vhost = $mailCred->vhost;

        if (! $vhost) {
            return ['migrated' => false, 'reason' => 'VHost not found'];
        }

        // Check if already migrated
        $existing = VPass::byOwner($vhost)
            ->where('pserv', 'dovecot')
            ->where('pname', $mailCred->email)
            ->first();

        if ($existing) {
            return ['migrated' => false, 'reason' => 'Already exists in VPass'];
        }

        // Create VPass entry
        VPass::create([
            'owner_type' => FleetVhost::class,
            'owner_id' => $vhost->id,
            'ptype' => 'VMAIL',
            'pserv' => 'dovecot',
            'pname' => $mailCred->email,
            'pdata' => $mailCred->cleartext_password, // Auto-encrypted by VPass model
            'pmeta' => [
                'migrated_from' => 'mail_credentials',
                'original_id' => $mailCred->id,
                'password_hint' => $mailCred->password_hint,
            ],
            'pstat' => $mailCred->is_active,
            'pdate' => $mailCred->last_rotated_at,
            'pnote' => $mailCred->notes,
            'created_at' => $mailCred->created_at,
            'updated_at' => $mailCred->updated_at,
        ]);

        return ['migrated' => true];
    }
}
