<?php

namespace NetServa\Mail\Filament;

use Filament\Panel;
use NetServa\Core\Foundation\BaseFilamentPlugin;
use NetServa\Mail\Filament\Clusters\Mail\MailCluster;
use NetServa\Mail\Filament\Resources\MailAliasResource;
use NetServa\Mail\Filament\Resources\MailboxResource;
use NetServa\Mail\Filament\Resources\MailDomainResource;
use NetServa\Mail\Filament\Resources\MailLogResource;
use NetServa\Mail\Filament\Resources\MailQueueResource;
use NetServa\Mail\Filament\Resources\MailServerResource;

/**
 * NetServa Mail Plugin
 *
 * Provides comprehensive email server management for NetServa infrastructure.
 * Integrates with Postfix, Dovecot, and supports virtual mailboxes and domains.
 *
 * Features:
 * - Mailbox management (virtual users)
 * - Mail domain configuration
 * - Mail server (Postfix/Dovecot) setup
 * - Mail queue monitoring
 * - Mail log analysis
 * - Mail alias and forwarding rules
 */
class NetServaMailPlugin extends BaseFilamentPlugin
{
    protected array $dependencies = ['netserva-core', 'netserva-dns'];

    public function getId(): string
    {
        return 'netserva-mail';
    }

    protected function registerResources(Panel $panel): void
    {
        // Register cluster for collapsible navigation
        $panel->clusters([
            MailCluster::class,
        ]);

        $panel->resources([
            MailDomainResource::class,
            MailboxResource::class,
            MailAliasResource::class,
            MailServerResource::class,
            MailQueueResource::class,
            MailLogResource::class,
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
        // Planned groups: Mail Services
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
                'virtual_mailboxes' => true,
                'mail_domains' => true,
                'mail_aliases' => true,
                'queue_monitoring' => true,
                'log_analysis' => true,
                'smtp_relay' => true,
            ],
            'settings' => [
                'default_quota' => '1G',
                'max_message_size' => '25M',
                'smtp_server' => 'postfix',
                'imap_server' => 'dovecot',
            ],
        ];
    }
}
