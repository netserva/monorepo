<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Mail\Models\MailDomain;
use NetServa\Mail\Models\MailServer;

class MailDomainFactory extends Factory
{
    protected $model = MailDomain::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Mail Domain',
            'domain' => fake()->unique()->domainName(),
            'mail_server_id' => MailServer::factory(),
            'is_active' => true,

            // Security features (matches migration)
            'enable_dkim' => fake()->boolean(),
            'enable_spf' => fake()->boolean(),
            'enable_dmarc' => fake()->boolean(),

            // Relay configuration (matches migration)
            'relay_enabled' => false,
            'relay_host' => null,
            'relay_port' => null,

            // Basic info (matches migration)
            'description' => fake()->sentence(),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
            'created_by' => 'system',
        ];
    }
}
