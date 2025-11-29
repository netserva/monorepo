<?php

namespace NetServa\Core\Validation\Rules;

/**
 * Shared Password Validation Rules
 *
 * Used by both console commands and Filament forms to ensure consistent
 * password validation across the application.
 */
class PasswordRules
{
    /**
     * Secure password rules for user accounts
     *
     * Requires:
     * - At least 12 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one number
     */
    public static function secure(): array
    {
        return [
            'required',
            'string',
            'min:12',
            'regex:/[A-Z]/',      // At least one uppercase
            'regex:/[a-z]/',      // At least one lowercase
            'regex:/[0-9]/',      // At least one number
        ];
    }

    /**
     * Strong password rules for admin/system accounts
     *
     * Additional requirements:
     * - At least 16 characters
     * - Special character required
     */
    public static function strong(): array
    {
        return [
            'required',
            'string',
            'min:16',
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
            'regex:/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', // Special character
        ];
    }

    /**
     * Basic password rules (for temporary passwords)
     */
    public static function basic(): array
    {
        return [
            'required',
            'string',
            'min:8',
        ];
    }

    /**
     * Get validation error messages
     */
    public static function messages(): array
    {
        return [
            'min' => 'Password must be at least :min characters long',
            'regex' => 'Password must contain uppercase, lowercase, and numeric characters',
        ];
    }

    /**
     * Get strong password error messages
     */
    public static function strongMessages(): array
    {
        return [
            'min' => 'Password must be at least :min characters long',
            'regex' => 'Password must contain uppercase, lowercase, numeric, and special characters',
        ];
    }
}
