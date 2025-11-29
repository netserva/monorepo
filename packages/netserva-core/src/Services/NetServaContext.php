<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * NetServa Context Management Service
 *
 * Manages current VNODE context and command history for ergonomic CLI usage
 */
class NetServaContext
{
    protected const CONTEXT_FILE = '/.ns/tmp/current_context';

    protected const CONTEXT_CACHE_KEY = 'netserva_current_context';

    protected const CONTEXT_TTL = 3600; // 1 hour

    /**
     * Get current VNODE from context
     */
    public function getCurrentShost(): ?string
    {
        // Try cache first
        $context = Cache::get(self::CONTEXT_CACHE_KEY);
        if ($context && isset($context['VNODE'])) {
            return $context['VNODE'];
        }

        // Try context file
        $contextFile = env('HOME', '/root').self::CONTEXT_FILE;
        if (File::exists($contextFile)) {
            $content = File::get($contextFile);
            if (preg_match('/VNODE=([^\s]+)/', $content, $matches)) {
                $VNODE = $matches[1];
                $this->setCurrentShost($VNODE); // Update cache

                return $VNODE;
            }
        }

        return null;
    }

    /**
     * Set current VNODE context
     */
    public function setCurrentShost(string $VNODE): void
    {
        $context = [
            'VNODE' => $VNODE,
            'timestamp' => time(),
        ];

        // Update cache
        Cache::put(self::CONTEXT_CACHE_KEY, $context, self::CONTEXT_TTL);

        // Update context file
        $contextFile = env('HOME', '/root').self::CONTEXT_FILE;
        $contextDir = dirname($contextFile);

        if (! File::exists($contextDir)) {
            File::makeDirectory($contextDir, 0755, true);
        }

        $content = "# NetServa Current Context\n";
        $content .= '# Updated: '.date('Y-m-d H:i:s')."\n";
        $content .= "VNODE={$VNODE}\n";

        File::put($contextFile, $content);
    }

    /**
     * Clear current context
     */
    public function clearContext(): void
    {
        Cache::forget(self::CONTEXT_CACHE_KEY);

        $contextFile = env('HOME', '/root').self::CONTEXT_FILE;
        if (File::exists($contextFile)) {
            File::delete($contextFile);
        }
    }

    /**
     * Get context information
     */
    public function getContextInfo(): array
    {
        $VNODE = $this->getCurrentShost();

        return [
            'VNODE' => $VNODE,
            'env_VNODE' => env('VNODE'),
            'env_VHOST' => env('VHOST'),
            'context_active' => ! is_null($VNODE),
        ];
    }

    /**
     * Add command to history
     */
    public function addToHistory(string $command, array $details = []): void
    {
        $history = Cache::get('netserva_command_history', []);

        $entry = [
            'command' => $command,
            'timestamp' => time(),
            'VNODE' => $this->getCurrentShost(),
            'details' => $details,
        ];

        array_unshift($history, $entry);

        // Keep only last 50 commands
        $history = array_slice($history, 0, 50);

        Cache::put('netserva_command_history', $history, 86400); // 24 hours
    }

    /**
     * Get recent command history
     */
    public function getHistory(int $limit = 10): array
    {
        $history = Cache::get('netserva_command_history', []);

        return array_slice($history, 0, $limit);
    }
}
