<?php

namespace NetServa\Core\Console\Commands;

/**
 * Clear Context Command
 *
 * Clear default VNODE context: php artisan clear-context
 */
class ClearContextCommand extends BaseNetServaCommand
{
    protected $signature = 'clear-context {--dry-run : Show what would be done}';

    protected $description = 'Clear default SSH host context';

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $currentShost = $this->context->getCurrentShost();

            $this->context->clearContext();

            if ($currentShost) {
                $this->info("✅ Cleared server context (was: <fg=yellow>{$currentShost}</>)");
            } else {
                $this->info('✅ Context cleared (no context was set)');
            }

            $this->line('');
            $this->line('<fg=blue>🎯 Now you must specify --shost for commands:</>');
            $this->line('   <fg=gray>addvhost test.motd.com --shost=motd</>');
            $this->line('   <fg=gray>use-server motd</>');

            return 0;
        });
    }
}
