<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AddpwCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addpw {howmany=1 : Number of passwords to generate} {length=16 : Length of each password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate secure passwords (NetServa CRUD pattern, replaces newpw)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $howmany = (int) $this->argument('howmany');
        $length = (int) $this->argument('length');

        // Validate input
        if ($howmany < 1 || $howmany > 100) {
            $this->error('❌ Number of passwords must be between 1 and 100');

            return 1;
        }

        if ($length < 8 || $length > 128) {
            $this->error('❌ Password length must be between 8 and 128 characters');

            return 1;
        }

        // Generate passwords
        for ($i = 0; $i < $howmany; $i++) {
            $password = $this->generateSecurePassword($length);
            $this->line($password);
        }

        return 0;
    }

    /**
     * Generate secure password matching newpw script behavior
     * Guarantees at least one uppercase, lowercase, and digit
     */
    private function generateSecurePassword(int $length): string
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
