<?php

namespace NetServa\Cli\Filament;

use Filament\Panel;
use NetServa\Cli\Filament\Resources\MigrationJobResource;
use NetServa\Cli\Filament\Resources\SetupComponentResource;
use NetServa\Cli\Filament\Resources\SetupJobResource;
use NetServa\Cli\Filament\Resources\SetupTemplateResource;
use NetServa\Cli\Filament\Widgets\MigrationDashboardWidget;
use NetServa\Core\Foundation\BaseFilamentPlugin;

/**
 * NetServa CLI Filament Plugin
 *
 * Provides Filament panel integration for NetServa CLI management.
 * Extends BaseFilamentPlugin for enhanced features like dependency management,
 * versioning, and per-panel configuration.
 *
 * Features:
 * - Migration job management
 * - Setup template and component management
 * - Deployment job tracking
 *
 * Usage in PanelProvider:
 *
 * ```php
 * use NetServa\Cli\Filament\NetServaCliPlugin;
 *
 * public function panel(Panel $panel): Panel
 * {
 *     return $panel
 *         ->plugin(NetServaCliPlugin::make()
 *             ->migrationResources(true)
 *             ->setupResources(true)
 *         );
 * }
 * ```
 */
class NetServaCliPlugin extends BaseFilamentPlugin
{
    /**
     * Plugin depends on netserva-core
     */
    protected array $dependencies = ['netserva-core'];

    /**
     * Feature toggles
     */
    protected bool $hasMigrationResources = true;

    protected bool $hasSetupResources = true;

    /**
     * Get the unique plugin identifier
     */
    public function getId(): string
    {
        return 'netserva-cli';
    }

    /**
     * Register CLI management resources
     */
    protected function registerResources(Panel $panel): void
    {
        $resources = [];

        // Register migration resources if enabled
        if ($this->hasMigrationResources()) {
            $resources[] = MigrationJobResource::class;
        }

        // Register setup resources if enabled
        if ($this->hasSetupResources()) {
            $resources[] = SetupTemplateResource::class;
            $resources[] = SetupComponentResource::class;
            $resources[] = SetupJobResource::class;
        }

        if (! empty($resources)) {
            $panel->resources($resources);
        }
    }

    /**
     * Register pages (none currently)
     */
    protected function registerPages(Panel $panel): void
    {
        // No custom pages currently
    }

    /**
     * Register widgets
     */
    protected function registerWidgets(Panel $panel): void
    {
        $panel->widgets([
            MigrationDashboardWidget::class,
        ]);
    }

    /**
     * Configure navigation grouping
     */
    protected function registerNavigationItems(Panel $panel): void
    {
        // TODO: Navigation groups should be defined in Resource classes as protected static properties
        // This is the Filament 4.x pattern. For now, resources will use default navigation.
        //
        // Planned group: CLI Management
        // - MigrationJobResource
        // - SetupTemplateResource, SetupComponentResource, SetupJobResource
    }

    /**
     * CLI-specific boot logic
     */
    protected function bootPlugin(Panel $panel): void
    {
        // Additional boot logic if needed
    }

    /**
     * Enable/disable migration resources
     */
    public function migrationResources(bool $condition = true): static
    {
        $this->hasMigrationResources = $condition;

        return $this;
    }

    /**
     * Check if migration resources are enabled
     */
    public function hasMigrationResources(): bool
    {
        return $this->hasMigrationResources;
    }

    /**
     * Enable/disable setup resources
     */
    public function setupResources(bool $condition = true): static
    {
        $this->hasSetupResources = $condition;

        return $this;
    }

    /**
     * Check if setup resources are enabled
     */
    public function hasSetupResources(): bool
    {
        return $this->hasSetupResources;
    }

    /**
     * Set navigation group for all resources
     */
    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        // Update all resource navigation groups
        if ($this->hasMigrationResources()) {
            MigrationJobResource::$navigationGroup = $group;
        }

        if ($this->hasSetupResources()) {
            SetupTemplateResource::$navigationGroup = $group;
            SetupComponentResource::$navigationGroup = $group;
            SetupJobResource::$navigationGroup = $group;
        }

        return $this;
    }

    /**
     * Get navigation group
     */
    public function getNavigationGroup(): string
    {
        return $this->navigationGroup;
    }

    /**
     * Set navigation sort order
     */
    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * Get navigation sort order
     */
    public function getNavigationSort(): int
    {
        return $this->navigationSort;
    }
}
