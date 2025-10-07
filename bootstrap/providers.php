<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,

    // NetServa Core Services (Load CLI first to claim 'ns' command)
    NetServa\Core\NetServaCoreServiceProvider::class,
    NetServa\Cli\NetServaCliServiceProvider::class,

    // NS Plugin Service Providers (Complete implementations)
    Ns\Core\CoreServiceProvider::class,
    Ns\Ssh\SshServiceProvider::class,
    Ns\Dns\DnsServiceProvider::class,
    Ns\Setup\SetupServiceProvider::class,
    Ns\Platform\PlatformServiceProvider::class,
    Ns\Secrets\SecretsServiceProvider::class,
    Ns\Ssl\SslServiceProvider::class,
    Ns\Ipam\IpamServiceProvider::class,
    Ns\Audit\AuditServiceProvider::class,
    Ns\Domain\DomainServiceProvider::class,
    Ns\Wireguard\WireguardServiceProvider::class,
    Ns\Web\WebServiceProvider::class,
    Ns\Backup\BackupServiceProvider::class,
    Ns\Monitor\MonitorServiceProvider::class,
    Ns\Analytics\AnalyticsServiceProvider::class,
    Ns\Automation\AutomationServiceProvider::class,
    Ns\Compliance\ComplianceServiceProvider::class,
    Ns\Config\ConfigServiceProvider::class,
    Ns\Database\DatabaseServiceProvider::class,
    Ns\Mail\MailServiceProvider::class,
    Ns\Migration\MigrationServiceProvider::class,
];
