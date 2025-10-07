<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Log;

/**
 * Logging Service
 *
 * Provides centralized logging for the NetServa ecosystem with context awareness.
 */
class LoggingService
{
    protected string $channel;

    protected string $defaultLevel;

    protected bool $includeContext;

    public function __construct(ConfigurationService $config)
    {
        $this->channel = $config->get('logging.channel', 'netserva');
        $this->defaultLevel = $config->get('logging.default_level', 'info');
        $this->includeContext = $config->get('logging.include_context', true);
    }

    /**
     * Log an emergency message
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Log an alert message
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Log a critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log a notice message
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log a message with the specified level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->includeContext) {
            $context = array_merge($this->getDefaultContext(), $context);
        }

        Log::channel($this->channel)->log($level, "[NetServa] {$message}", $context);
    }

    /**
     * Get default context for all log messages
     */
    protected function getDefaultContext(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'user_id' => auth()->id(),
        ];
    }

    /**
     * Log for a specific plugin
     */
    public function plugin(string $pluginId, string $level, string $message, array $context = []): void
    {
        $context['plugin_id'] = $pluginId;
        $this->log($level, "[{$pluginId}] {$message}", $context);
    }
}
