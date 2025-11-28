<?php

namespace NetServa\Core\Contracts;

/**
 * Configuration Manager Interface
 *
 * Common interface for managing NetServa configuration files,
 * ensuring consistency across VHost and SSH config services.
 */
interface ConfigManagerInterface
{
    /**
     * Load configuration from filesystem
     *
     * @param  string  $identifier  Configuration identifier (host, vhost, etc.)
     * @return array Configuration data as associative array
     */
    public function load(string $identifier): array;

    /**
     * Save configuration to filesystem
     *
     * @param  string  $identifier  Configuration identifier
     * @param  array  $config  Configuration data
     * @return bool Success status
     */
    public function save(string $identifier, array $config): bool;

    /**
     * Check if configuration exists
     *
     * @param  string  $identifier  Configuration identifier
     * @return bool True if configuration exists
     */
    public function exists(string $identifier): bool;

    /**
     * Delete configuration
     *
     * @param  string  $identifier  Configuration identifier
     * @return bool Success status
     */
    public function delete(string $identifier): bool;

    /**
     * List all available configurations
     *
     * @return array List of configuration identifiers
     */
    public function list(): array;

    /**
     * Validate configuration structure
     *
     * @param  array  $config  Configuration data
     * @return bool True if valid
     */
    public function validate(array $config): bool;

    /**
     * Backup configuration before changes
     *
     * @param  string  $identifier  Configuration identifier
     * @return string|null Backup file path on success, null on failure
     */
    public function backup(string $identifier): ?string;
}
