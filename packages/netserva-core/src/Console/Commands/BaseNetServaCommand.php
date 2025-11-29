<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\NetServaContext;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base NetServa Command
 *
 * Provides environment variable support and context management for all NetServa commands
 */
abstract class BaseNetServaCommand extends Command
{
    protected NetServaContext $context;

    public function __construct()
    {
        parent::__construct();
        $this->context = app(NetServaContext::class);
    }

    /**
     * Get VNODE/VNODE with environment variable fallback
     * Supports both legacy --shost and new --vnode parameters
     */
    protected function getShost(): ?string
    {
        // 1. Command line argument (highest priority) - check both vnode and shost
        if ($this->hasOption('vnode') && $this->option('vnode')) {
            return $this->option('vnode');
        }
        if ($this->hasOption('shost') && $this->option('shost')) {
            return $this->option('shost');
        }

        // 2. Environment variable - check both VNODE and VNODE
        if ($envVnode = env('VNODE')) {
            return $envVnode;
        }
        if ($envShost = env('VNODE')) {
            return $envShost;
        }

        // 3. Context cache (last used server)
        if ($contextShost = $this->context->getCurrentShost()) {
            return $contextShost;
        }

        return null;
    }

    /**
     * Get VHOST with smart defaults
     */
    protected function getVhost(?string $argument = null): ?string
    {
        // 1. Command argument (highest priority)
        if ($argument) {
            return $argument;
        }

        // 2. Environment variable
        if ($envVhost = env('VHOST')) {
            return $envVhost;
        }

        return null;
    }

    /**
     * Require VNODE/VNODE or fail with helpful message
     */
    protected function requireShost(): string
    {
        $VNODE = $this->getShost();

        if (! $VNODE) {
            $this->error('❌ VNODE required. Use one of:');
            $this->line('  • --vnode=motd (recommended)');
            $this->line('  • --shost=motd (legacy)');
            $this->line('  • export VNODE=motd');
            $this->line('  • export VNODE=motd (legacy)');
            $this->line('  • php artisan use-server motd');

            exit(1);
        }

        // Cache for subsequent commands
        $this->context->setCurrentShost($VNODE);

        return $VNODE;
    }

    /**
     * Require VHOST or fail with helpful message
     */
    protected function requireVhost(?string $argument = null): string
    {
        $VHOST = $this->getVhost($argument);

        if (! $VHOST) {
            $this->error('❌ VHOST required as first argument');
            $this->line('  Usage: '.$this->signature);

            exit(1);
        }

        return $VHOST;
    }

    /**
     * Display context information
     */
    protected function showContext(): void
    {
        $VNODE = $this->getShost();
        $VHOST = env('VHOST');

        if ($VNODE || $VHOST) {
            $this->line('<fg=blue>📍 Current Context:</>');
            if ($VNODE) {
                $this->line("   VNODE: <fg=yellow>{$VNODE}</>");
            }
            if ($VHOST) {
                $this->line("   VHOST: <fg=yellow>{$VHOST}</>");
            }
            $this->line('');
        }
    }

    /**
     * Get standard NetServa options
     */
    protected function getNetServaOptions(): array
    {
        return [
            ['shost', 's', InputOption::VALUE_OPTIONAL, 'SSH host identifier (or use VNODE env var)'],
            ['dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing'],
            ['verbose', 'v', InputOption::VALUE_NONE, 'Show detailed output'],
        ];
    }

    /**
     * Execute with error handling and context display
     */
    protected function executeWithContext(callable $callback): int
    {
        try {
            if ($this->hasOption('verbose') && $this->option('verbose')) {
                $this->showContext();
            }

            return $callback() ?? 0;

        } catch (\Exception $e) {
            $this->error('❌ '.$e->getMessage());

            if ($this->hasOption('verbose') && $this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Show what would be executed in dry-run mode
     */
    protected function dryRun(string $description, array $commands = []): void
    {
        $this->line("<fg=yellow>🔍 DRY RUN:</> {$description}");

        foreach ($commands as $command) {
            $this->line("   <fg=gray>→</> {$command}");
        }
    }
}
