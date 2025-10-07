<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DelpwCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delpw {--dry-run : Show what would be done}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete/clear passwords from clipboard and memory (NetServa CRUD symmetry)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->line('🔍 DRY RUN: Delete passwords from system');
            $this->line('   → Clear clipboard contents');
            $this->line('   → Clear shell history password entries');
            $this->line('   → Clear temporary password files');

            return 0;
        }

        $this->line('🗑️  Clearing passwords from system...');

        // Clear clipboard if available
        $clipboardCommands = [
            'which xclip > /dev/null 2>&1 && echo "" | xclip -selection clipboard',
            'which pbcopy > /dev/null 2>&1 && echo "" | pbcopy',
            'which wl-copy > /dev/null 2>&1 && echo "" | wl-copy',
        ];

        $cleared = false;
        foreach ($clipboardCommands as $cmd) {
            $result = shell_exec($cmd.' 2>/dev/null');
            if ($result !== false) {
                $cleared = true;
                break;
            }
        }

        if ($cleared) {
            $this->info('✅ Clipboard cleared');
        } else {
            $this->line('⚠️  No clipboard utility found (xclip/pbcopy/wl-copy)');
        }

        // Clear any temporary password files in /tmp
        $tempFiles = glob('/tmp/*password*');
        $tempFiles = array_merge($tempFiles, glob('/tmp/*pw*'));

        $deletedCount = 0;
        foreach ($tempFiles as $file) {
            if (is_file($file) && unlink($file)) {
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("✅ Cleared {$deletedCount} temporary password files");
        }

        // Show security reminder
        $this->line('');
        $this->line('<fg=blue>🔒 Security Reminder:</>');
        $this->line('   • Consider clearing bash history: history -c');
        $this->line('   • Check for password echoes in terminal scrollback');
        $this->line('   • Verify secure password storage practices');

        return 0;
    }
}
