<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Mail\Models\MailAlias;

class MailAliasFactory extends Factory
{
    protected $model = MailAlias::class;

    public function definition(): array
    {
        $domain = fake()->domainName();
        $localPart = fake()->userName();

        return [
            // Simple clean schema matching migration
            'alias_email' => $localPart.'@'.$domain,
            'destination_emails' => json_encode([fake()->email(), fake()->email()]),
            'is_active' => true,
            'description' => fake()->sentence(),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
            'created_by' => 'system',
        ];
    }
}
