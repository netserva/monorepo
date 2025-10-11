<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VmailManagementService;

/**
 * Add Virtual Mail Command
 *
 * Follows NetServa CRUD pattern: addvmail (not "ns vmail add")
 * Usage: addvmail user@domain.com [--vnode=motd] [password]
 * With context: export VNODE=motd; addvmail admin@motd.com
 */
class AddvmailCommand extends BaseNetServaCommand
{
    protected $signature = 'addvmail {email : Email address to add} {password? : Optional password (auto-generated if not provided)} {--vnode= : Virtual node identifier} {--shost= : SSH host identifier (legacy)} {--dry-run : Show what would be done}';

    protected $description = 'Add a new virtual mail user (NetServa CRUD pattern)';

    protected VmailManagementService $vmailService;

    public function __construct(VmailManagementService $vmailService)
    {
        parent::__construct();
        $this->vmailService = $vmailService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters using NetServa patterns
            $email = strtolower($this->argument('email')); // Force lowercase like bash script
            $password = $this->argument('password');
            $VNODE = $this->requireShost(); // Still returns VNODE internally for compatibility

            // Validate email format
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("❌ Invalid email format: {$email}");

                return 1;
            }

            // Extract domain from email
            $VHOST = substr(strstr($email, '@'), 1);
            $VUSER = substr($email, 0, strpos($email, '@'));

            // Generate password if not provided (using Laravel addpw command)
            if (! $password) {
                $password = $this->generateSecurePassword();
            }

            // Show what we're about to do
            $this->line("📧 Adding Virtual Mail User: <fg=yellow>{$email}</> on node <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Add Virtual Mail User {$email} on {$VNODE}", [
                    "Validate VHost {$VHOST} exists in fleet_vhosts table",
                    "Check if {$email} already exists",
                    'Create mailbox entry in vmails table',
                    'Create alias entry in valias table',
                    'Create mail log entry in vmail_log table',
                    'Update vconfs table with mail credentials (database-first)',
                    "SSH to {$VNODE} and create Maildir structure via heredoc script",
                    'Set up SpamProbe filters',
                    'Set correct file permissions',
                ]);

                return 0;
            }

            // Create the virtual mail user using NetServa service
            $result = $this->vmailService->createVmailUser($VNODE, $email, $password);

            if ($result['success']) {
                $this->info("✅ Virtual mail user {$email} created successfully on {$VNODE}");

                // Show key NetServa information
                if (isset($result['details'])) {
                    $details = $result['details'];
                    $this->line('');
                    $this->line('<fg=blue>📧 Mail User Details:</>');
                    $this->line("   User: <fg=yellow>{$VUSER}@{$VHOST}</>");
                    $this->line("   Maildir: <fg=yellow>{$details['maildir']}</>");
                    $this->line("   Password: <fg=yellow>{$password}</>");
                    $this->line("   Config: <fg=green>vconfs table</> (database-first)");
                }

                // Add to command history
                $this->context->addToHistory("addvmail {$email}", [
                    'VNODE' => $VNODE,
                    'EMAIL' => $email,
                    'VHOST' => $VHOST,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to create virtual mail user {$email} on {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    /**
     * Generate secure password using same algorithm as addpw command
     */
    private function generateSecurePassword(int $length = 12): string
    {
        // Character sets (exclude 'O' to avoid confusion with '0')
        $charset = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $upperChars = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $lowerChars = 'abcdefghijklmnopqrstuvwxyz';
        $digitChars = '0123456789';

        // Guarantee one of each required type
        $upperChar = $upperChars[random_int(0, strlen($upperChars) - 1)];
        $lowerChar = $lowerChars[random_int(0, strlen($lowerChars) - 1)];
        $digitChar = $digitChars[random_int(0, strlen($digitChars) - 1)];

        // Fill the rest with random characters
        $remainingLength = $length - 3;
        $rest = '';

        for ($i = 0; $i < $remainingLength; $i++) {
            $rest .= $charset[random_int(0, strlen($charset) - 1)];
        }

        // Combine all parts
        $combined = $upperChar.$lowerChar.$digitChar.$rest;

        // Shuffle the characters
        $password = str_shuffle($combined);

        // Replace any 'O' with '0' as backup (though we excluded 'O' from charset)
        $password = str_replace('O', '0', $password);

        // Ensure exact length
        return substr($password, 0, $length);
    }
}
