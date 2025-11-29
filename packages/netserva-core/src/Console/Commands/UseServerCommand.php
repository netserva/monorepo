<?php

namespace NetServa\Core\Console\Commands;

/**
 * Use Server Command
 *
 * Set default VNODE context: php artisan use-server motd
 * Then subsequent commands don't need --vnode: addvhost test.motd.com
 */
class UseServerCommand extends BaseNetServaCommand
{
    protected $signature = 'use-server {vnode : Virtual node identifier to use as default} {--dry-run : Show what would be done}';

    protected $description = 'Set default virtual node context for subsequent commands';

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');

            // Validate VNODE exists (basic check)
            if (! $VNODE || strlen($VNODE) < 1) {
                $this->error('❌ Invalid VNODE provided');

                return 1;
            }

            // Set the context (still uses internal VNODE for compatibility)
            $this->context->setCurrentShost($VNODE);

            $this->info("✅ Default node context set to: <fg=yellow>{$VNODE}</>");
            $this->line('');
            $this->line('<fg=blue>🎯 Now you can run commands without --vnode:</>');
            $this->line("   <fg=gray>addvhost test.{$VNODE}.com</>");
            $this->line('   <fg=gray>listvhosts</>');
            $this->line('   <fg=gray>status</>');
            $this->line('');
            $this->line('<fg=gray>💡 Use "clear-context" to reset</>');

            return 0;
        });
    }
}
