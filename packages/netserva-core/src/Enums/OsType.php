<?php

namespace NetServa\Core\Enums;

/**
 * Operating System Types for NetServa Platform
 *
 * Canonical OS types supported by NetServa with their configuration defaults
 */
enum OsType: string
{
    case ALPINE = 'alpine';
    case DEBIAN = 'debian';
    case UBUNTU = 'ubuntu';
    case MANJARO = 'manjaro';
    case CACHYOS = 'cachyos';

    /**
     * Get default OS release for each type
     */
    public function getDefaultRelease(): string
    {
        return match ($this) {
            self::ALPINE => 'latest-stable',
            self::DEBIAN => 'bookworm',
            self::UBUNTU => 'jammy',
            self::MANJARO => 'stable',
            self::CACHYOS => 'n/a',
        };
    }

    /**
     * Get default package mirror for each OS type
     */
    public function getDefaultMirror(): string
    {
        return match ($this) {
            self::ALPINE => 'dl-cdn.alpinelinux.org',
            self::DEBIAN => 'deb.debian.org',
            self::UBUNTU => 'archive.ubuntu.com',
            self::MANJARO => 'manjaro.moson.eu',
            self::CACHYOS => 'archlinux.cachyos.org',
        };
    }

    /**
     * Get web server user/group for OS type
     */
    public function getWebUserGroup(): string
    {
        return match ($this) {
            self::ALPINE => 'nginx',
            self::MANJARO, self::CACHYOS => 'http',
            self::DEBIAN, self::UBUNTU => 'www-data',
        };
    }

    /**
     * Get PHP version string for OS type
     */
    public function getPhpVersion(): string
    {
        return match ($this) {
            self::ALPINE => '84',
            self::DEBIAN, self::UBUNTU => '8.4',
            self::MANJARO, self::CACHYOS => '8.4',
        };
    }

    /**
     * Get PHP-FPM configuration path
     */
    public function getPhpFpmPath(): string
    {
        return match ($this) {
            self::ALPINE => '/etc/php84',
            self::DEBIAN, self::UBUNTU => '/etc/php/8.4/fpm',
            self::MANJARO, self::CACHYOS => '/etc/php',
        };
    }

    /**
     * Get DNS server configuration path
     */
    public function getDnsPath(): string
    {
        return match ($this) {
            self::ALPINE => '/etc/pdns',
            self::MANJARO, self::CACHYOS => '/etc/powerdns',
            self::DEBIAN, self::UBUNTU => '/etc/powerdns',
        };
    }

    /**
     * Get MySQL configuration path
     */
    public function getMysqlPath(): string
    {
        return match ($this) {
            self::ALPINE, self::MANJARO, self::CACHYOS => '/etc/my.cnf.d',
            self::DEBIAN, self::UBUNTU => '/etc/mysql',
        };
    }

    /**
     * Create from string with fallback to Debian
     */
    public static function fromString(string $osType): self
    {
        return self::tryFrom(strtolower($osType)) ?? self::DEBIAN;
    }

    /**
     * Check if OS is Arch-based
     */
    public function isArchBased(): bool
    {
        return $this === self::MANJARO || $this === self::CACHYOS;
    }

    /**
     * Check if OS is Debian-based
     */
    public function isDebianBased(): bool
    {
        return $this === self::DEBIAN || $this === self::UBUNTU;
    }
}
