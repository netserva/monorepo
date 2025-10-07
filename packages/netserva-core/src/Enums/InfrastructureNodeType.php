<?php

namespace NetServa\Core\Enums;

/**
 * NetServa Core Platform Node Types
 *
 * Standardized platform node types across the NetServa ecosystem.
 * Part of the NetServa Core foundation package.
 */
enum PlatformNodeType: string
{
    case HOST = 'host';
    case SERVER = 'server';
    case SERVICE = 'service';
    case CONTAINER = 'container';
    case VM = 'vm';
    case VPS = 'vps';
    case CLUSTER = 'cluster';
    case NETWORK = 'network';
    case STORAGE = 'storage';
    case DATABASE = 'database';
    case LOAD_BALANCER = 'load_balancer';
    case PROXY = 'proxy';
    case CDN = 'cdn';
    case DNS = 'dns';
    case SSL = 'ssl';
    case BACKUP = 'backup';
    case MONITORING = 'monitoring';
    case APPLICATION = 'application';
    case DOMAIN = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::HOST => 'Host',
            self::SERVER => 'Server',
            self::SERVICE => 'Service',
            self::CONTAINER => 'Container',
            self::VM => 'Virtual Machine',
            self::VPS => 'Virtual Private Server',
            self::CLUSTER => 'Cluster',
            self::NETWORK => 'Network',
            self::STORAGE => 'Storage',
            self::DATABASE => 'Database',
            self::LOAD_BALANCER => 'Load Balancer',
            self::PROXY => 'Proxy',
            self::CDN => 'Content Delivery Network',
            self::DNS => 'DNS Server',
            self::SSL => 'SSL Certificate',
            self::BACKUP => 'Backup Service',
            self::MONITORING => 'Monitoring Service',
            self::APPLICATION => 'Application',
            self::DOMAIN => 'Domain',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HOST => 'Physical or virtual host machine',
            self::SERVER => 'Server instance running services',
            self::SERVICE => 'Software service or daemon',
            self::CONTAINER => 'LXC container or similar',
            self::VM => 'Virtual machine instance',
            self::VPS => 'Virtual private server from cloud provider',
            self::CLUSTER => 'Group of related nodes',
            self::NETWORK => 'Network platform component',
            self::STORAGE => 'Storage system or volume',
            self::DATABASE => 'Database server or instance',
            self::LOAD_BALANCER => 'Load balancing service',
            self::PROXY => 'Proxy server or service',
            self::CDN => 'Content delivery network service',
            self::DNS => 'DNS resolution service',
            self::SSL => 'SSL/TLS certificate management',
            self::BACKUP => 'Backup and restore service',
            self::MONITORING => 'System monitoring service',
            self::APPLICATION => 'Application deployment',
            self::DOMAIN => 'Domain name registration',
        };
    }

    public function isPhysical(): bool
    {
        return in_array($this, [
            self::HOST,
            self::SERVER,
        ]);
    }

    public function isVirtual(): bool
    {
        return in_array($this, [
            self::CONTAINER,
            self::VM,
            self::VPS,
        ]);
    }

    public function isService(): bool
    {
        return in_array($this, [
            self::SERVICE,
            self::DATABASE,
            self::LOAD_BALANCER,
            self::PROXY,
            self::CDN,
            self::DNS,
            self::BACKUP,
            self::MONITORING,
            self::APPLICATION,
        ]);
    }

    public function isInfrastructure(): bool
    {
        return in_array($this, [
            self::NETWORK,
            self::STORAGE,
            self::SSL,
            self::DOMAIN,
        ]);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function physicalTypes(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isPhysical())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function virtualTypes(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isVirtual())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function serviceTypes(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->isService())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
