<?php

namespace NetServa\Core\DataObjects;

use Illuminate\Console\Command;

/**
 * User Password Data Transfer Object
 *
 * Standardizes data structure for password operations across
 * console commands and Filament forms.
 */
readonly class UserPasswordData
{
    public function __construct(
        public string $vnode,
        public string $email,
        public string $password,
        public bool $generateHash = true,
        public ?string $passwordType = 'mail', // mail, database, system
    ) {}

    /**
     * Create from console command input
     */
    public static function fromConsoleInput(Command $command): self
    {
        return new self(
            vnode: $command->argument('vnode'),
            email: $command->argument('email'),
            password: $command->argument('password'),
            generateHash: ! $command->option('plain'),
            passwordType: $command->option('type') ?? 'mail',
        );
    }

    /**
     * Create from Filament form data
     */
    public static function fromFilamentForm(array $data): self
    {
        return new self(
            vnode: $data['vnode'],
            email: $data['email'],
            password: $data['password'],
            generateHash: $data['generate_hash'] ?? true,
            passwordType: $data['password_type'] ?? 'mail',
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'vnode' => $this->vnode,
            'email' => $this->email,
            'password' => $this->password,
            'generate_hash' => $this->generateHash,
            'password_type' => $this->passwordType,
        ];
    }

    /**
     * Get the domain from email
     */
    public function getDomain(): string
    {
        return substr($this->email, strpos($this->email, '@') + 1);
    }

    /**
     * Get the username from email
     */
    public function getUsername(): string
    {
        return substr($this->email, 0, strpos($this->email, '@'));
    }

    /**
     * Check if this is a valid email format
     */
    public function isValidEmail(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
