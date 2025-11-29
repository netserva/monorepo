<?php

namespace NetServa\Core\Validation\Rules;

/**
 * Shared Domain Validation Rules
 *
 * Used by both console commands and Filament forms to ensure consistent
 * domain/vhost validation across the application.
 */
class DomainRules
{
    /**
     * Standard domain name validation
     *
     * Validates:
     * - Valid domain format (example.com)
     * - Subdomain support (www.example.com)
     * - No spaces or special characters except hyphen and dot
     */
    public static function domain(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
        ];
    }

    /**
     * Optional domain validation (nullable)
     */
    public static function domainNullable(): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
        ];
    }

    /**
     * Subdomain validation (allows wildcards)
     */
    public static function subdomain(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'regex:/^(\*\.)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
        ];
    }

    /**
     * Validate domain exists in vhost configurations
     */
    public static function existsInVhostConfigs(string $vnode): array
    {
        return [
            ...self::domain(),
            'exists:vhost_configurations,vhost,vnode,'.$vnode,
        ];
    }

    /**
     * Validate domain is unique for a vnode
     */
    public static function uniqueForVnode(string $vnode, ?int $exceptId = null): array
    {
        $rule = 'unique:vhost_configurations,vhost,NULL,id,vnode,'.$vnode;

        if ($exceptId) {
            $rule .= ','.$exceptId;
        }

        return [
            ...self::domain(),
            $rule,
        ];
    }

    /**
     * Get validation error messages
     */
    public static function messages(): array
    {
        return [
            'regex' => 'The :attribute must be a valid domain name (e.g., example.com)',
            'max' => 'The :attribute must not exceed :max characters',
            'exists' => 'The domain does not exist on the specified server',
            'unique' => 'This domain already exists on the specified server',
        ];
    }
}
