<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Remote Execution Command
 *
 * Execute arbitrary bash scripts on remote vnodes without escaping hell.
 * Wraps RemoteExecutionService::executeScript() for ad-hoc command execution.
 *
 * Usage:
 *   php artisan remote:exec nsorg 'ls -la /srv'
 *   php artisan remote:exec nsorg 'for dir in packages/*\/; do echo $dir; done'
 *   rex nsorg 'mysql -e "SELECT * FROM vhosts WHERE uid=1000"'
 *
 * Why this exists:
 *   Complex bash commands with loops, pipes, quotes need proper heredoc handling.
 *   This command eliminates shell escaping issues by using Laravel's RemoteExecutionService.
 */
class RemoteExecCommand extends BaseNetServaCommand
{
    protected $signature = 'remote:exec
                            {vnode : Virtual node to execute on}
                            {script : Bash script to execute (use quotes for complex commands)}
                            {--no-root : Execute without root privileges}
                            {--dry-run : Show what would be executed without running}';

    protected $description = 'Execute bash script on remote vnode (eliminates escaping hell)';

    public function handle(RemoteExecutionService $remoteExec): int
    {
        return $this->executeWithContext(function () use ($remoteExec) {
            $vnodeName = $this->argument('vnode');
            $script = $this->argument('script');
            $asRoot = ! $this->option('no-root');
            $dryRun = $this->option('dry-run');

            // Lookup vnode in database
            $vnode = FleetVnode::with('sshHost')->where('name', $vnodeName)->first();

            if (! $vnode) {
                $this->error("❌ Vnode '{$vnodeName}' not found in database");
                $this->line('');
                $this->line('💡 Available vnodes:');
                FleetVnode::orderBy('name')->pluck('name')->each(fn ($name) => $this->line("   • {$name}"));

                return 1;
            }

            // Validate SSH host configuration
            if (! $vnode->sshHost) {
                $this->error("❌ Vnode '{$vnodeName}' has no SSH host configured");
                $this->line('');
                $this->line('💡 Configure SSH access first:');
                $this->line("   php artisan fleet:discover {$vnodeName}");

                return 1;
            }

            // Get SSH host identifier (e.g., "mrn", "nsorg") - not the IP/hostname
            $sshHost = $vnode->sshHost->host;
            $displayHost = $vnode->sshHost->hostname ?? $sshHost;

            // Show what we're doing
            if ($dryRun) {
                $this->dryRun(
                    "Execute on {$vnodeName} ({$displayHost}) ".($asRoot ? '(as root)' : '(as user)'),
                    [$script]
                );

                return 0;
            }

            // Verbose mode shows context
            if ($this->option('verbose')) {
                $this->line("<fg=blue>📡 Executing on:</> <fg=yellow>{$vnodeName}</> ({$displayHost})");
                $this->line('');
            }

            // Execute script via RemoteExecutionService
            $result = $remoteExec->executeScript(
                host: $sshHost,
                script: $script,
                args: [],
                asRoot: $asRoot,
                dryRun: false
            );

            // Display raw output (like sx does)
            if ($result['output']) {
                echo $result['output'];
            }

            // Verbose mode shows status
            if ($this->option('verbose')) {
                if ($result['success']) {
                    $this->newLine();
                    $this->info("✅ Command completed successfully (exit code: {$result['return_code']})");
                } else {
                    $this->newLine();
                    $this->error("❌ Command failed (exit code: {$result['return_code']})");
                    if (isset($result['error'])) {
                        $this->error("   {$result['error']}");
                    }
                }
            }

            return $result['success'] ? 0 : 1;
        });
    }
}
