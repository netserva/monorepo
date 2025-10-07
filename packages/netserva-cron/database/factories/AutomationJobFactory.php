<?php

namespace NetServa\Cron\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Cron\Models\AutomationJob;
use NetServa\Cron\Models\AutomationTask;

class AutomationJobFactory extends Factory
{
    protected $model = AutomationJob::class;

    public function definition(): array
    {
        return [
            'job_name' => $this->faker->words(3, true),
            'automation_task_id' => AutomationTask::factory(),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed']),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'started_at' => $this->faker->optional()->dateTime(),
            'completed_at' => $this->faker->optional()->dateTime(),
            'execution_time_seconds' => $this->faker->numberBetween(1, 3600),
            'command_executed' => $this->faker->sentence(),
            'stdout' => $this->faker->optional()->text(),
            'stderr' => $this->faker->optional()->text(),
            'exit_code' => $this->faker->numberBetween(0, 255),
            'error_message' => $this->faker->optional()->sentence(),
            'target_host' => $this->faker->ipv4(),
            'target_user' => $this->faker->userName(),
            'progress_percent' => $this->faker->numberBetween(0, 100),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'metadata' => ['key' => $this->faker->word()],
            'created_by' => $this->faker->name(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'progress_percent' => 0,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
            'progress_percent' => $this->faker->numberBetween(1, 99),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
            'progress_percent' => 100,
            'exit_code' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
            'exit_code' => 1,
            'error_message' => $this->faker->sentence(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }
}
