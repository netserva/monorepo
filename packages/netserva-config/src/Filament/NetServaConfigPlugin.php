<?php

namespace NetServa\Config\Filament;

use Filament\Panel;
use NetServa\Config\Filament\Resources\ConfigDeploymentResource;
use NetServa\Config\Filament\Resources\ConfigProfileResource;
use NetServa\Config\Filament\Resources\ConfigTemplateResource;
use NetServa\Config\Filament\Resources\ConfigVariableResource;
use NetServa\Config\Filament\Resources\DatabaseConnectionResource;
use NetServa\Config\Filament\Resources\DatabaseCredentialResource;
use NetServa\Config\Filament\Resources\DatabaseResource;
use NetServa\Config\Filament\Resources\SecretAccessResource;
use NetServa\Config\Filament\Resources\SecretResource;
use NetServa\Core\Foundation\BaseFilamentPlugin;

/**
 * NetServa Config Plugin
 *
 * Provides centralized configuration and secrets management for NetServa infrastructure.
 * Handles templates, profiles, database credentials, and secure secret storage.
 *
 * Features:
 * - Configuration template management
 * - Configuration profiles and variables
 * - Database connection management
 * - Database credential storage
 * - Secret management with access control
 * - Configuration deployment tracking
 *
 * @package NetServa\Config\Filament
 */
class NetServaConfigPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core'];

    public function getId(): string
    {
        return 'netserva-config';
    }

    protected function registerResources(Panel $panel): void
    {
        $panel->resources([
            ConfigTemplateResource::class,
            ConfigProfileResource::class,
            ConfigVariableResource::class,
            ConfigDeploymentResource::class,
            DatabaseResource::class,
            DatabaseConnectionResource::class,
            DatabaseCredentialResource::class,
            SecretResource::class,
            SecretAccessResource::class,
        ]);
    }

    protected function registerPages(Panel $panel): void
    {
        // No custom pages currently
    }

    protected function registerWidgets(Panel $panel): void
    {
        // No widgets currently
    }

    protected function registerNavigationItems(Panel $panel): void
    {
        // TODO: Navigation groups should be defined in Resource classes as protected static properties
        // This is the Filament 4.x pattern. For now, resources will use default navigation.
        //
        // Planned groups: Configuration, Databases, Secrets
    }

    public function getVersion(): string
    {
        return '3.0.0';
    }

    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [
                'config_templates' => true,
                'config_profiles' => true,
                'database_management' => true,
                'secret_management' => true,
                'deployment_tracking' => true,
            ],
            'settings' => [
                'encryption_enabled' => true,
                'secret_retention_days' => 90,
                'audit_logging' => true,
                'template_versioning' => true,
            ],
        ];
    }
}
