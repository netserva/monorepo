<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\ConfigTemplate;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Config\Models\ConfigTemplate>
 */
class ConfigTemplateFactory extends Factory
{
    protected $model = ConfigTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Basic identification - from model's fillable array
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'config_type' => $this->faker->randomElement(['nginx', 'apache', 'php', 'mysql']),
            'category' => $this->faker->randomElement(['web', 'mail', 'database', 'system']),

            // Template content
            'template_content' => $this->faker->text(500),
            'required_variables' => ['server_name', 'port'],
            'variable_defaults' => ['ssl_enabled' => false],

            // File deployment
            'target_filename' => $this->faker->word().'.conf',
            'target_path' => '/etc/'.$this->faker->word(),
            'file_permissions' => '0644',
            'file_owner' => 'root',
            'file_group' => 'root',

            // Basic settings
            'is_active' => true,
            'requires_service_restart' => false,
            'restart_commands' => [],
            'enable_backup' => true,

            // Simple validation
            'syntax_check_command' => null,

            // Basic metadata
            'tags' => ['config', 'deployment'],
            'metadata' => ['author' => 'system'],
        ];
    }

    /**
     * Indicate that the template is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality_status' => 'draft',
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is stable.
     */
    public function stable(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality_status' => 'stable',
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the template is for nginx.
     */
    public function nginx(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_type' => 'nginx',
            'category' => 'web',
            'target_filename' => 'nginx.conf',
            'target_path' => '/etc/nginx',
        ]);
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'quality_status' => 'stable',
        ]);
    }

    /**
     * Indicate that the template is a base template.
     */
    public function baseTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base_template' => true,
            'quality_status' => 'stable',
            'is_active' => true,
            'parent_template_id' => null,
        ]);
    }

    /**
     * Indicate that the template is a child template.
     */
    public function childTemplate($parentTemplateId = null): static
    {
        return $this->state(function (array $attributes) use ($parentTemplateId) {
            if ($parentTemplateId) {
                return [
                    'is_base_template' => false,
                    'parent_template_id' => $parentTemplateId,
                ];
            }

            // Create a parent template if no ID provided
            $parentTemplate = ConfigTemplate::factory()->baseTemplate()->create();

            return [
                'is_base_template' => false,
                'parent_template_id' => $parentTemplate->id,
            ];
        });
    }
}
