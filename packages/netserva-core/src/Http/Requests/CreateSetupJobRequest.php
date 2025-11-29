<?php

namespace NetServa\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use NetServa\Core\Validation\Rules\VhostRules;

/**
 * Create Setup Job Request
 *
 * Validates data for creating a new setup/deployment job.
 */
class CreateSetupJobRequest extends FormRequest
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
            'job_name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'integer', 'exists:setup_templates,id'],
            'target_host' => VhostRules::vnodeExists(),
            'description' => ['nullable', 'string', 'max:1000'],
            'configuration' => ['nullable', 'array'],
            'dry_run' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return array_merge(
            VhostRules::messages(),
            [
                'template_id.exists' => 'The selected template does not exist',
                'priority.min' => 'Priority must be at least 0',
                'priority.max' => 'Priority must not exceed 100',
            ]
        );
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'job_name' => 'job name',
            'template_id' => 'template',
            'target_host' => 'target server',
            'description' => 'description',
            'configuration' => 'configuration',
            'dry_run' => 'dry run',
            'priority' => 'priority',
        ];
    }

    /**
     * Get validated data with defaults.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'dry_run' => false,
            'priority' => 0,
            'configuration' => null,
            'status' => 'pending',
            'progress' => 0,
        ], $validated);
    }
}
