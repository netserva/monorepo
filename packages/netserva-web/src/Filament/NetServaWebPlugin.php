<?php

namespace NetServa\Web\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Web\Filament\Clusters\Web\WebCluster;
use NetServa\Web\Filament\Resources\SslCertificateDeploymentResource;
use NetServa\Web\Filament\Resources\SslCertificateResource;
use NetServa\Web\Filament\Resources\VirtualHostResource;
use NetServa\Web\Filament\Resources\WebApplicationResource;
use NetServa\Web\Filament\Resources\WebServerResource;

/**
 * NetServa Web Plugin
 *
 * Provides web server and SSL certificate management for NetServa infrastructure.
 * Integrates with nginx, PHP-FPM, and Let's Encrypt.
 *
 * Features:
 * - Virtual host (vhost) management
 * - Web server configuration
 * - SSL certificate provisioning and renewal
 * - Web application deployment tracking
 * - Nginx and PHP-FPM integration
 */
class NetServaWebPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core', 'netserva-dns'];

    public function getId(): string
    {
        return 'netserva-web';
    }

    protected function registerResources(Panel $panel): void
    {
        // Register cluster for collapsible navigation
        $panel->clusters([
            WebCluster::class,
        ]);

        $panel->resources([
            VirtualHostResource::class,
            WebServerResource::class,
            WebApplicationResource::class,
            SslCertificateResource::class,
            SslCertificateDeploymentResource::class,
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
        // Planned groups: Web Services
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
                'virtual_hosts' => true,
                'web_servers' => true,
                'web_applications' => true,
                'ssl_certificates' => true,
                'auto_ssl_renewal' => true,
            ],
            'settings' => [
                'default_web_server' => 'nginx',
                'default_php_version' => '8.4',
                'ssl_provider' => 'letsencrypt',
            ],
        ];
    }
}
