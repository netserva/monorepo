<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Config;

/**
 * Configuration Service
 *
 * Provides centralized configuration management for the NetServa ecosystem.
 */
class ConfigurationService
{
    /**
     * Get a configuration value with fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Config::get("netserva-core.{$key}", $default);
    }

    /**
     * Set a configuration value
     */
    public function set(string $key, mixed $value): void
    {
        Config::set("netserva-core.{$key}", $value);
    }

    /**
     * Check if a configuration key exists
     */
    public function has(string $key): bool
    {
        return Config::has("netserva-core.{$key}");
    }

    /**
     * Get all configuration as array
     */
    public function all(): array
    {
        return Config::get('netserva-core', []);
    }

    /**
     * Get plugin-specific configuration
     */
    public function getPluginConfig(string $pluginId): array
    {
        return $this->get("plugins.{$pluginId}", []);
    }

    /**
     * Set plugin-specific configuration
     */
    public function setPluginConfig(string $pluginId, array $config): void
    {
        $this->set("plugins.{$pluginId}", $config);
    }
}
