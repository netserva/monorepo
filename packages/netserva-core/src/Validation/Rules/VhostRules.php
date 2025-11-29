<?php

namespace NetServa\Core\Validation\Rules;

use NetServa\Core\Enums\OsType;

/**
 * Shared VHost Validation Rules
 *
 * Combines domain, server, and configuration validation for virtual hosts.
 */
class VhostRules
{
    /**
     * Server node (vnode/shost) validation
     */
    public static function vnode(): array
    {
        return [
            'required',
            'string',
            'alpha_dash',
            'max:64',
        ];
    }

    /**
     * Server node with existence check
     */
    public static function vnodeExists(): array
    {
        return [
            ...self::vnode(),
            'exists:ssh_hosts,host',
        ];
    }

    /**
     * VHost domain validation (alias for DomainRules)
     */
    public static function domain(): array
    {
        return DomainRules::domain();
    }

    /**
     * PHP version validation
     */
    public static function phpVersion(): array
    {
        return [
            'required',
            'string',
            'regex:/^[5-8]\.\d{1,2}$/',
            'in:7.4,8.0,8.1,8.2,8.3,8.4',
        ];
    }

    /**
     * Optional PHP version
     */
    public static function phpVersionNullable(): array
    {
        return [
            'nullable',
            'string',
            'regex:/^[5-8]\.\d{1,2}$/',
            'in:7.4,8.0,8.1,8.2,8.3,8.4',
        ];
    }

    /**
     * Database type validation
     */
    public static function databaseType(): array
    {
        return [
            'required',
            'string',
            'in:mysql,sqlite,postgresql',
        ];
    }

    /**
     * OS type validation
     */
    public static function osType(): array
    {
        return [
            'required',
            'string',
            'in:'.implode(',', array_column(OsType::cases(), 'value')),
        ];
    }

    /**
     * Unix username validation
     */
    public static function unixUsername(): array
    {
        return [
            'required',
            'string',
            'regex:/^[a-z_][a-z0-9_-]{0,31}$/',
            'max:32',
        ];
    }

    /**
     * Unix UID validation
     */
    public static function unixUid(): array
    {
        return [
            'required',
            'integer',
            'min:1000',
            'max:65535',
        ];
    }

    /**
     * File path validation
     */
    public static function filePath(): array
    {
        return [
            'required',
            'string',
            'regex:/^\/[a-zA-Z0-9\/_-]+$/',
            'max:4096',
        ];
    }

    /**
     * Optional file path validation
     */
    public static function filePathNullable(): array
    {
        return [
            'nullable',
            'string',
            'regex:/^\/[a-zA-Z0-9\/_-]+$/',
            'max:4096',
        ];
    }

    /**
     * IP address validation
     */
    public static function ipAddress(): array
    {
        return [
            'required',
            'ip',
        ];
    }

    /**
     * Optional IP address validation
     */
    public static function ipAddressNullable(): array
    {
        return [
            'nullable',
            'ip',
        ];
    }

    /**
     * Get validation error messages
     */
    public static function messages(): array
    {
        return [
            'vnode.exists' => 'The selected server node does not exist',
            'vnode.alpha_dash' => 'The server node may only contain letters, numbers, dashes and underscores',
            'php_version.regex' => 'PHP version must be in format X.Y (e.g., 8.4)',
            'php_version.in' => 'PHP version must be one of: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4',
            'unix_username.regex' => 'Username must start with a letter or underscore, contain only lowercase letters, numbers, underscores, and hyphens',
            'unix_uid.min' => 'UID must be at least 1000 (system UIDs are reserved)',
            'file_path.regex' => 'File path must be an absolute path starting with /',
            ...DomainRules::messages(),
        ];
    }
}
