<?php

namespace NetServa\Core\ValueObjects;

use NetServa\Core\Enums\OsType;

/**
 * Operating System Configuration Value Object
 *
 * Type-safe container for OS-specific NetServa configuration using ENUMs
 */
readonly class OsConfiguration
{
    public function __construct(
        public OsType $type,
        public string $release,
        public string $mirror
    ) {}

    /**
     * Create from string values with enum conversion
     */
    public static function fromStrings(string $type, ?string $release = null, ?string $mirror = null): self
    {
        $osType = OsType::fromString($type);

        return new self(
            type: $osType,
            release: $release ?? $osType->getDefaultRelease(),
            mirror: $mirror ?? $osType->getDefaultMirror()
        );
    }

    /**
     * Create with defaults for OS type
     */
    public static function forType(OsType $type): self
    {
        return new self(
            type: $type,
            release: $type->getDefaultRelease(),
            mirror: $type->getDefaultMirror()
        );
    }

    /**
     * Get package manager
     */
    public function getPackageManager(): string
    {
        return match ($this->type) {
            OsType::ALPINE => 'apk',
            OsType::DEBIAN, OsType::UBUNTU => 'apt',
            OsType::MANJARO, OsType::CACHYOS => 'pacman',
        };
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'release' => $this->release,
            'mirror' => $this->mirror,
        ];
    }
}
