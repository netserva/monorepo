<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\ConfigDeployment;
use NetServa\Config\Models\ConfigProfile;
use NetServa\Core\Models\InfrastructureNode;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Config\Models\ConfigDeployment>
 */
class ConfigDeploymentFactory extends Factory
{
    protected $model = ConfigDeployment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Basic identification - from model's fillable array
            'deployment_id' => $this->faker->uuid(),
            'deployment_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),

            // Source configuration
            'config_profile_id' => ConfigProfile::factory(),
            'infrastructure_node_id' => InfrastructureNode::factory(),

            // Deployment configuration
            'deployment_method' => $this->faker->randomElement(['ssh', 'api', 'local']),
            'templates_to_deploy' => ['template1', 'template2'],
            'variables_used' => ['var1' => 'value1', 'var2' => 'value2'],

            // Status and timing
            'status' => $this->faker->randomElement(['pending', 'deploying', 'completed', 'failed']),
            'started_at' => null,
            'completed_at' => null,

            // Results
            'success' => false,
            'deployed_files' => ['file1.conf', 'file2.conf'],

            // Error handling
            'error_message' => null,

            // Environment
            'deployment_environment' => $this->faker->randomElement(['development', 'staging', 'production']),
        ];
    }

    /**
     * Indicate that the deployment is completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'success' => true,
            'progress_percentage' => 100,
            'templates_deployed' => $this->faker->numberBetween(1, 10),
            'templates_failed' => 0,
            'error_message' => null,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'total_time_seconds' => 3600,
        ]);
    }

    /**
     * Indicate that the deployment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'success' => false,
            'templates_failed' => $this->faker->numberBetween(1, 5),
            'error_message' => 'Deployment failed due to configuration error',
            'error_severity' => 'high',
            'rollback_required' => true,
        ]);
    }

    /**
     * Indicate that the deployment is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deploying',
            'progress_percentage' => $this->faker->numberBetween(1, 99),
            'success' => false,
            'started_at' => now()->subMinutes($this->faker->numberBetween(1, 60)),
        ]);
    }

    /**
     * Indicate that the deployment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'progress_percentage' => 0,
            'success' => false,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the deployment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'success' => false,
            'progress_percentage' => 0,
            'templates_deployed' => 0,
            'error_message' => 'Deployment cancelled by user',
            'started_at' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the deployment is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'trigger_type' => 'scheduled',
            'scheduled_at' => now()->addHour(),
            'progress_percentage' => 0,
            'started_at' => null,
        ]);
    }

    /**
     * Indicate that the deployment has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'trigger_type' => 'manual',
            'deployment_environment' => 'production',
        ]);
    }
}
