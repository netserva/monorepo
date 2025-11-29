<?php

namespace NetServa\Core\Validation\Rules;

/**
 * Shared Email Validation Rules
 *
 * Used by both console commands and Filament forms to ensure consistent
 * email validation across the application.
 */
class EmailRules
{
    /**
     * Standard email validation
     */
    public static function email(): array
    {
        return [
            'required',
            'string',
            'email:rfc,dns',
            'max:255',
        ];
    }

    /**
     * Optional email validation
     */
    public static function emailNullable(): array
    {
        return [
            'nullable',
            'string',
            'email:rfc,dns',
            'max:255',
        ];
    }

    /**
     * Email validation without DNS check (faster, less strict)
     */
    public static function emailBasic(): array
    {
        return [
            'required',
            'string',
            'email:rfc',
            'max:255',
        ];
    }

    /**
     * Validate email belongs to a specific domain
     */
    public static function emailForDomain(string $domain): array
    {
        return [
            ...self::email(),
            'regex:/^[a-zA-Z0-9._%+-]+@'.preg_quote($domain, '/').'$/',
        ];
    }

    /**
     * Multiple emails validation (comma-separated)
     */
    public static function multipleEmails(): array
    {
        return [
            'required',
            'string',
            'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(,\s*[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})*$/',
        ];
    }

    /**
     * Get validation error messages
     */
    public static function messages(): array
    {
        return [
            'email' => 'The :attribute must be a valid email address',
            'regex' => 'The :attribute must be a valid email address for the specified domain',
            'max' => 'The :attribute must not exceed :max characters',
        ];
    }
}
