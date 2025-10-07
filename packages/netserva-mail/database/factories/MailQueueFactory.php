<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Mail\Models\MailQueue;

class MailQueueFactory extends Factory
{
    protected $model = MailQueue::class;

    public function definition(): array
    {
        return [
            // Simple clean schema matching migration
            'message_id' => fake()->uuid().'@example.com',
            'sender' => fake()->email(),
            'recipient' => fake()->email(),
            'subject' => fake()->sentence(),
            'status' => fake()->randomElement(['queued', 'processing', 'sent', 'deferred', 'bounced', 'failed']),
            'attempts' => fake()->numberBetween(0, 3),
            'next_retry_at' => fake()->optional()->dateTimeBetween('now', '+1 hour'),
            'error_message' => fake()->optional()->sentence(),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'attempts' => 0,
            'next_retry_at' => null,
            'error_message' => null,
        ]);
    }

    public function deferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deferred',
            'attempts' => fake()->numberBetween(1, 3),
            'next_retry_at' => fake()->dateTimeBetween('+10 minutes', '+1 hour'),
            'error_message' => fake()->sentence(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bounced',
            'attempts' => fake()->numberBetween(3, 5),
            'error_message' => 'Maximum delivery attempts exceeded',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'attempts' => fake()->numberBetween(3, 5),
            'error_message' => fake()->sentence(),
        ]);
    }
}
