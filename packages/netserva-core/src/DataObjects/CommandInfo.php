<?php

declare(strict_types=1);

namespace NetServa\Core\DataObjects;

/**
 * Data Transfer Object for Artisan Command information
 *
 * Represents a parsed artisan command with its signature,
 * arguments, options, and metadata for the Command Runner UI.
 */
class CommandInfo
{
    /**
     * @param  array<string, array{name: string, description: string, required: bool, default: mixed}>  $arguments
     * @param  array<string, array{name: string, description: string, required: bool, default: mixed, accepts_value: bool, is_array: bool}>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $signature,
        public readonly string $package,
        public readonly string $className,
        public readonly array $arguments = [],
        public readonly array $options = [],
        public readonly bool $isDangerous = false,
        public readonly bool $isHidden = false,
    ) {}

    /**
     * Get unique identifier for the command
     */
    public function getId(): string
    {
        return md5($this->name);
    }

    /**
     * Check if command has any required arguments
     */
    public function hasRequiredArguments(): bool
    {
        foreach ($this->arguments as $arg) {
            if ($arg['required']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if command has any arguments or options
     */
    public function hasParameters(): bool
    {
        return count($this->arguments) > 0 || count($this->options) > 0;
    }

    /**
     * Get badge color based on command type
     */
    public function getBadgeColor(): string
    {
        if ($this->isDangerous) {
            return 'danger';
        }

        return match (true) {
            str_starts_with($this->name, 'add') => 'success',
            str_starts_with($this->name, 'sh') => 'info',
            str_starts_with($this->name, 'ch') => 'warning',
            str_starts_with($this->name, 'del') => 'danger',
            str_contains($this->name, ':discover') => 'primary',
            str_contains($this->name, ':sync') => 'primary',
            str_contains($this->name, 'migrate') => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get command type label
     */
    public function getTypeLabel(): string
    {
        return match (true) {
            str_starts_with($this->name, 'add') => 'Create',
            str_starts_with($this->name, 'sh') => 'Read',
            str_starts_with($this->name, 'ch') => 'Update',
            str_starts_with($this->name, 'del') => 'Delete',
            str_contains($this->name, ':discover') => 'Discovery',
            str_contains($this->name, ':sync') => 'Sync',
            str_contains($this->name, 'migrate') => 'Migration',
            str_contains($this->name, 'install') => 'Install',
            str_contains($this->name, 'test') => 'Test',
            default => 'System',
        };
    }

    /**
     * Convert to array for table display
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'description' => $this->description,
            'signature' => $this->signature,
            'package' => $this->package,
            'class_name' => $this->className,
            'arguments' => $this->arguments,
            'options' => $this->options,
            'is_dangerous' => $this->isDangerous,
            'is_hidden' => $this->isHidden,
            'type' => $this->getTypeLabel(),
            'badge_color' => $this->getBadgeColor(),
            'has_parameters' => $this->hasParameters(),
        ];
    }
}
