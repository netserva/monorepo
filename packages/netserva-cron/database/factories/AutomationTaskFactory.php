<?php

namespace NetServa\Cron\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Cron\Models\AutomationTask;

class AutomationTaskFactory extends Factory
{
    protected $model = AutomationTask::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'task_type' => $this->faker->randomElement(['shell', 'ssh', 'script']),
            'command' => $this->faker->sentence(),
            'target_host' => $this->faker->optional()->ipv4() ?? 'localhost',
            'target_user' => $this->faker->optional()->userName() ?? 'root',
            'timeout_seconds' => $this->faker->numberBetween(30, 3600),
            'max_retries' => $this->faker->numberBetween(0, 5),
            'retry_delay_seconds' => $this->faker->numberBetween(5, 60),
            'is_active' => $this->faker->boolean(80),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'priority' => $this->faker->randomElement([1, 2, 3]),
            'success_rate' => $this->faker->randomFloat(2, 0, 100),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'metadata' => ['category' => $this->faker->word()],
            'created_by' => $this->faker->name(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 3,
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 1,
        ]);
    }

    public function shell(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'shell',
        ]);
    }

    public function ssh(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'ssh',
        ]);
    }

    public function script(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'script',
        ]);
    }
}
