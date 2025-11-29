<?php

namespace NetServa\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use NetServa\Core\Validation\Rules\EmailRules;
use NetServa\Core\Validation\Rules\PasswordRules;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Update Password Request
 *
 * Validates data for updating user passwords (mail accounts, system users, etc).
 */
class UpdatePasswordRequest extends FormRequest
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
        return [
            'vnode' => VhostRules::vnodeExists(),
            'email' => EmailRules::email(),
            'password' => PasswordRules::secure(),
            'password_type' => ['nullable', 'string', 'in:mail,database,system'],
            'generate_hash' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return array_merge(
            VhostRules::messages(),
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
            'email' => 'email address',
            'password' => 'password',
            'password_type' => 'password type',
            'generate_hash' => 'generate hash',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize email to lowercase
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }
}
