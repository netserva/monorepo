<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\VmailManagementService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Add Virtual Mail Command
 *
 * NetServa 3.0 CRUD: CREATE operation for virtual mailboxes
 * Usage: addvmail <vnode> <email> [password]
 */
class AddvmailCommand extends Command
{
    protected $signature = 'addvmail
                            {vnode : The vnode to create on}
                            {email : Email address to add}
                            {password? : Optional password (auto-generated if not provided)}';

    protected $description = 'Add a new virtual mail user (NetServa 3.0 CRUD: Create)';

    protected VmailManagementService $vmailService;

    public function __construct(VmailManagementService $vmailService)
    {
        parent::__construct();
        $this->vmailService = $vmailService;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $email = strtolower($this->argument('email')); // Force lowercase
        $password = $this->argument('password');

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email format: {$email}");

            return Command::FAILURE;
        }

        // Extract domain from email
        $domain = substr(strstr($email, '@'), 1);
        $localpart = substr($email, 0, strpos($email, '@'));

        // Generate password if not provided
        if (! $password) {
            $password = $this->generateSecurePassword();
        }

        $this->info("Creating virtual mail user: {$email} on {$vnodeName}");

        // NetServa 3.0: Get vnode from database
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode not found: {$vnodeName}");
            $this->warn("Run 'addfleet {$vnodeName}' first.");

            return Command::FAILURE;
        }

        // Create the virtual mail user using NetServa service
        $result = $this->vmailService->createVmailUser($vnodeName, $email, $password);

        if ($result['success']) {
            $this->info('✓ Virtual mail user created successfully');
            $this->newLine();

            // Show key information
            if (isset($result['details'])) {
                $details = $result['details'];
                $this->line('Mail User Details:');
                $this->line("  Email: {$email}");
                $this->line("  Maildir: {$details['maildir']}");
                $this->line("  Password: {$password}");
            }

            Log::info('Virtual mail user created', [
                'vnode' => $vnodeName,
                'email' => $email,
            ]);

            return Command::SUCCESS;
        } else {
            $this->error("Failed to create virtual mail user: {$email}");
            if (isset($result['error'])) {
                $this->line("  Error: {$result['error']}");
            }

            Log::error('Failed to create virtual mail user', [
                'vnode' => $vnodeName,
                'email' => $email,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            return Command::FAILURE;
        }
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
