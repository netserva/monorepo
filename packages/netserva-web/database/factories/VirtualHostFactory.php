<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Web\Models\VirtualHost;
use NetServa\Web\Models\WebServer;

class VirtualHostFactory extends Factory
{
    protected $model = VirtualHost::class;

    public function definition(): array
    {
        $domain = fake()->domainName();

        return [
            'name' => fake()->company().' Virtual Host',
            'server_names' => json_encode([$domain, 'www.'.$domain]),
            'primary_domain' => $domain,
            'description' => fake()->sentence(),
            'web_server_id' => WebServer::factory(),
            'is_active' => true,
            'is_default' => false,
            'status' => 'active',
            'document_root' => '/var/www/'.$domain,
            'index_files' => 'index.html index.htm index.php',
            'http_enabled' => true,
            'http_port' => 80,
            'https_enabled' => fake()->boolean(),
            'https_port' => 443,
            'force_https' => false,
            'ssl_enabled' => fake()->boolean(),
            'ssl_auto_renew' => true,
            'ssl_provider' => 'letsencrypt',
            'php_enabled' => true,
            'php_version' => '8.4',
            'php_handler' => 'fpm',
            'directory_listing' => false,
            'follow_symlinks' => false,
            'charset' => 'UTF-8',
            'client_max_body_size' => '64M',
            'static_compression' => true,
            'compression_types' => json_encode(['text/plain', 'text/css', 'application/json']),
            'caching_enabled' => false,
            'security_headers' => json_encode([
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
            ]),
            'rate_limiting' => false,
            'infrastructure_node_id' => 1,
            'access_log_enabled' => true,
            'access_log_path' => "/var/log/nginx/{$domain}_access.log",
            'error_log_enabled' => true,
            'error_log_path' => "/var/log/nginx/{$domain}_error.log",
            'is_responding' => true,
            'response_time_ms' => fake()->randomFloat(2, 50, 500),
            'uptime_percentage' => fake()->randomFloat(2, 95, 100),
            'health_status' => 'healthy',
            'deployment_status' => 'deployed',
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
        ];
    }

    public function withSsl(): static
    {
        return $this->state([
            'ssl_enabled' => true,
            'https_enabled' => true,
            'force_https' => true,
            'ssl_certificate_path' => '/etc/letsencrypt/live/{domain}/fullchain.pem',
            'ssl_private_key_path' => '/etc/letsencrypt/live/{domain}/privkey.pem',
        ]);
    }

    public function active(): static
    {
        return $this->state(['is_active' => true, 'status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false, 'status' => 'inactive']);
    }
}
