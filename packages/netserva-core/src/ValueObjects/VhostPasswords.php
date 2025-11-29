<?php

namespace NetServa\Core\ValueObjects;

/**
 * VHost Passwords Value Object
 *
 * Type-safe container for all NetServa VHost passwords.
 */
readonly class VhostPasswords
{
    public function __construct(
        public string $admin,
        public string $database,
        public string $email,
        public string $user,
        public string $web,
        public string $wordpress
    ) {}

    /**
     * Get all passwords as associative array
     */
    public function toArray(): array
    {
        return [
            'admin' => $this->admin,
            'database' => $this->database,
            'email' => $this->email,
            'user' => $this->user,
            'web' => $this->web,
            'wordpress' => $this->wordpress,
        ];
    }

    /**
     * Check if any password is empty
     */
    public function hasEmptyPasswords(): bool
    {
        return empty($this->admin) ||
               empty($this->database) ||
               empty($this->email) ||
               empty($this->user) ||
               empty($this->web) ||
               empty($this->wordpress);
    }
}
