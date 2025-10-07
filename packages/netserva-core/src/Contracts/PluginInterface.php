<?php

namespace NetServa\Core\Contracts;

use Filament\Panel;

interface PluginInterface
{
    /**
     * Get the plugin's unique identifier
     */
    public function getId(): string;

    /**
     * Get the plugin's display name
     */
    public function getName(): string;

    /**
     * Get the plugin's description
     */
    public function getDescription(): string;

    /**
     * Get the plugin's version
     */
    public function getVersion(): string;

    /**
     * Get the plugin's dependencies
     *
     * @return array<string> Array of plugin IDs this plugin depends on
     */
    public function getDependencies(): array;

    /**
     * Register plugin resources with Filament panel
     */
    public function registerResources(Panel $panel): void;

    /**
     * Register plugin pages with Filament panel
     */
    public function registerPages(Panel $panel): void;

    /**
     * Register plugin widgets with Filament panel
     */
    public function registerWidgets(Panel $panel): void;

    /**
     * Register console commands
     *
     * @return array<class-string> Array of command classes
     */
    public function getCommands(): array;

    /**
     * Check if the plugin is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable the plugin
     */
    public function enable(): void;

    /**
     * Disable the plugin
     */
    public function disable(): void;

    /**
     * Get configuration for the plugin
     */
    public function getConfig(): array;

    /**
     * Boot the plugin (called after all plugins are registered)
     */
    public function boot(): void;
}
