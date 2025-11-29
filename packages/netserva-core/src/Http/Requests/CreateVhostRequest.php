<?php

namespace NetServa\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use NetServa\Core\Validation\Rules\DomainRules;
use NetServa\Core\Validation\Rules\EmailRules;
use NetServa\Core\Validation\Rules\PasswordRules;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Create VHost Request
 *
 * Validates data for creating a new virtual host.
 * Can be used in both API endpoints and as validation reference for console/Filament.
 */
class CreateVhostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $vnode = $this->input('vnode');

        return [
            'vnode' => VhostRules::vnodeExists(),
            'vhost' => $vnode ? DomainRules::uniqueForVnode($vnode) : DomainRules::domain(),
            'php_version' => VhostRules::phpVersionNullable(),
            'ssl_enabled' => ['nullable', 'boolean'],
            'database_type' => ['nullable', ...VhostRules::databaseType()],
            'database_name' => ['nullable', 'string', 'alpha_dash', 'max:64'],
            'admin_email' => EmailRules::emailNullable(),
            'admin_password' => ['nullable', ...PasswordRules::secure()],
            'webroot' => VhostRules::filePathNullable(),
            'uid' => ['nullable', ...VhostRules::unixUid()],
            'gid' => ['nullable', ...VhostRules::unixUid()],
            'username' => ['nullable', ...VhostRules::unixUsername()],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return array_merge(
            VhostRules::messages(),
            DomainRules::messages(),
            EmailRules::messages(),
            PasswordRules::messages(),
        );
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'vnode' => 'server node',
            'vhost' => 'domain name',
            'php_version' => 'PHP version',
            'ssl_enabled' => 'SSL/TLS enabled',
            'database_type' => 'database type',
            'database_name' => 'database name',
            'admin_email' => 'administrator email',
            'admin_password' => 'administrator password',
            'webroot' => 'web document root',
            'uid' => 'user ID',
            'gid' => 'group ID',
            'username' => 'system username',
        ];
    }

    /**
     * Get validated data as array with defaults.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'php_version' => '8.4',
            'ssl_enabled' => true,
            'database_type' => 'sqlite',
            'database_name' => null,
            'admin_email' => null,
            'admin_password' => null,
            'webroot' => null,
            'uid' => null,
            'gid' => null,
            'username' => null,
        ], $validated);
    }
}
