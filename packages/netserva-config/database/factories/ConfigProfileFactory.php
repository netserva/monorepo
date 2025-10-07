<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\ConfigProfile;
use NetServa\Core\Models\InfrastructureNode;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Config\Models\ConfigProfile>
 */
class ConfigProfileFactory extends Factory
{
    protected $model = ConfigProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Profile identification - from model's fillable array
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),

            // Infrastructure targeting
            'infrastructure_node_id' => InfrastructureNode::factory(),

            // Environment classification
            'environment' => $this->faker->randomElement(['development', 'staging', 'production']),

            // Profile configuration
            'is_active' => true,
            'is_default' => false,

            // Template associations
            'template_assignments' => ['template1', 'template2'],

            // Variable configuration
            'global_variables' => ['app_name' => 'MyApp', 'debug' => true],

            // Basic deployment
            'deployment_method' => $this->faker->randomElement(['ssh', 'api', 'local']),
            'services_to_restart' => ['nginx', 'php-fpm'],
            'create_backup_before_deploy' => true,

            // Basic metadata
            'tags' => ['config', 'deployment'],
        ];
    }

    /**
     * Indicate that the profile is the default for its node.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'priority' => 100,
        ]);
    }

    /**
     * Indicate that the profile is for production.
     */
    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
            'require_approval' => true,
            'create_backup_before_deploy' => true,
            'enable_automatic_rollback' => true,
        ]);
    }

    /**
     * Indicate that the profile is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }
}
