<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Mail\Models\Mailbox;
use NetServa\Mail\Models\MailDomain;

class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    public function definition(): array
    {
        $domain = fake()->domainName();
        $localPart = fake()->userName();

        return [
            // Basic identification
            'email' => $localPart.'@'.$domain,
            'full_name' => fake()->name(),
            'description' => fake()->sentence(),
            'mail_domain_id' => MailDomain::factory(),

            // Authentication
            'password_hash' => '$2y$10$'.hash('sha256', fake()->password()),
            'is_active' => true,

            // Storage and quota
            'quota_bytes' => 1073741824, // 1GB
            'used_bytes' => fake()->numberBetween(0, 50000000), // Random usage up to ~50MB

            // Service permissions
            'enable_imap' => true,
            'enable_pop3' => fake()->boolean(30), // 30% chance of POP3 enabled

            // Forwarding
            'forward_to' => fake()->optional(0.2)->email(), // 20% chance of forwarding

            // Auto-reply
            'auto_reply_enabled' => fake()->boolean(10), // 10% chance
            'auto_reply_message' => fake()->optional(0.1)->sentence(),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
            'created_by' => 'system',
        ];
    }
}
