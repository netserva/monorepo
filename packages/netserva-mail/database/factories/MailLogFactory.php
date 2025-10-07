<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Mail\Models\MailLog;

class MailLogFactory extends Factory
{
    protected $model = MailLog::class;

    public function definition(): array
    {
        return [
            // Simple clean schema matching migration
            'timestamp' => fake()->dateTimeBetween('-1 week', 'now'),
            'level' => fake()->randomElement(['info', 'warning', 'error', 'debug']),
            'message' => fake()->sentence(),
            'sender' => fake()->optional()->email(),
            'recipient' => fake()->optional()->email(),
            'subject' => fake()->optional()->words(5, true),
            'message_id' => fake()->uuid().'@example.com',
            'server_component' => fake()->randomElement(['postfix', 'dovecot', 'rspamd', 'php-fpm']),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
        ];
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'error',
            'message' => 'Error: '.fake()->sentence(),
            'server_component' => fake()->randomElement(['postfix', 'dovecot', 'rspamd']),
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'warning',
            'message' => 'Warning: '.fake()->sentence(),
            'server_component' => fake()->randomElement(['postfix', 'dovecot', 'rspamd']),
        ]);
    }

    public function info(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'info',
            'message' => 'Info: '.fake()->sentence(),
            'server_component' => fake()->randomElement(['postfix', 'dovecot', 'rspamd']),
        ]);
    }
}
