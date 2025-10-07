<?php

namespace NetServa\Mail\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Mail\Models\MailServer;

class MailServerFactory extends Factory
{
    protected $model = MailServer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Mail Server',
            'hostname' => fake()->domainName(),
            'description' => fake()->sentence(),
            'infrastructure_node_id' => InfrastructureNode::factory(),
            'server_type' => fake()->randomElement(['postfix_dovecot', 'exim_dovecot', 'sendmail_courier']),
            'is_active' => true,
            'is_primary' => false,
            'public_ip' => fake()->ipv4(),

            // Port configuration (matches migration)
            'smtp_port' => 25,
            'imap_port' => 143,
            'pop3_port' => 110,

            // SSL configuration (matches migration)
            'enable_ssl' => true,
            'ssl_cert_path' => '/etc/ssl/certs/mail.crt',
            'ssl_key_path' => '/etc/ssl/private/mail.key',

            // Status (matches migration enum)
            'status' => fake()->randomElement(['healthy', 'warning', 'error', 'maintenance', 'unknown']),

            // Metadata (matches migration)
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
            'created_by' => 'system',
        ];
    }
}
