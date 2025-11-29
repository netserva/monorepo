<?php

namespace NetServa\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use NetServa\Core\Validation\Rules\DomainRules;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Create Migration Job Request
 *
 * Validates data for creating a new migration job.
 */
class CreateMigrationJobRequest extends FormRequest
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
            'source_server' => VhostRules::vnodeExists(),
            'target_server' => [...VhostRules::vnodeExists(), 'different:source_server'],
            'domain' => DomainRules::domain(),
            'job_name' => ['required', 'string', 'max:255'],
            'migration_type' => ['required', 'string', 'in:full,database-only,files-only,config-only'],
            'description' => ['nullable', 'string', 'max:1000'],
            'dry_run' => ['nullable', 'boolean'],
            'step_backup' => ['nullable', 'boolean'],
            'step_cleanup' => ['nullable', 'boolean'],
            'configuration' => ['nullable', 'array'],
            'ssh_host_id' => ['nullable', 'integer', 'exists:ssh_hosts,id'],
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
            [
                'target_server.different' => 'Target server must be different from source server',
                'migration_type.in' => 'Migration type must be one of: full, database-only, files-only, config-only',
            ]
        );
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'source_server' => 'source server',
            'target_server' => 'target server',
            'domain' => 'domain',
            'job_name' => 'job name',
            'migration_type' => 'migration type',
            'description' => 'description',
            'dry_run' => 'dry run',
            'step_backup' => 'backup step',
            'step_cleanup' => 'cleanup step',
            'configuration' => 'configuration',
            'ssh_host_id' => 'SSH host',
        ];
    }

    /**
     * Get validated data with defaults.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'migration_type' => 'full',
            'dry_run' => false,
            'step_backup' => true,
            'step_cleanup' => true,
            'configuration' => null,
            'ssh_host_id' => null,
            'status' => 'pending',
            'progress' => 0,
        ], $validated);
    }
}
