<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Web\Models\WebServer;

class WebServerFactory extends Factory
{
    protected $model = WebServer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Web Server',
            'hostname' => fake()->domainName(),
            'description' => fake()->sentence(),
            'infrastructure_node_id' => InfrastructureNode::factory(),
            'server_type' => fake()->randomElement(['nginx', 'apache', 'caddy', 'lighttpd']),
            'is_active' => true,
            'is_primary' => false,
            'version' => fake()->randomElement(['1.24.0', '2.4.58', '2.7.6', '1.14.2']),
            'public_ip' => fake()->ipv4(),
            'listen_addresses' => json_encode(['*:80', '*:443']),
            'port_config' => [
                'http' => 80,
                'https' => 443,
            ],
            'server_name' => fake()->domainName(),
            'config_path' => '/etc/nginx',
            'sites_path' => '/etc/nginx/sites-available',
            'enabled_path' => '/etc/nginx/sites-enabled',
            'document_root' => '/var/www/html',
            'log_path' => '/var/log/nginx',
            'enable_ssl' => fake()->boolean(),
            'ssl_protocols' => json_encode(['TLSv1.2', 'TLSv1.3']),
            'ssl_redirect' => true,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000,
            'worker_processes' => fake()->randomElement([1, 2, 4, 'auto']),
            'worker_connections' => fake()->randomElement([1024, 2048, 4096]),
            'keepalive_timeout' => 65,
            'client_max_body_size' => '64M',
            'gzip_enabled' => true,
            'gzip_types' => json_encode(['text/plain', 'text/css', 'application/json']),
            'server_tokens' => false,
            'security_headers' => json_encode([
                'X-Frame-Options' => 'DENY',
                'X-Content-Type-Options' => 'nosniff',
            ]),
            'rate_limiting_enabled' => false,
            'php_enabled' => true,
            'default_php_version' => '8.4',
            'php_versions' => json_encode(['8.4', '8.3']),
            'caching_enabled' => false,
            'load_balancing_enabled' => false,
            'health_checks_enabled' => true,
            'health_check_interval' => 30,
            'access_log_enabled' => true,
            'access_log_format' => 'combined',
            'error_log_enabled' => true,
            'error_log_level' => 'error',
            'service_status' => 'running',
            'health_status' => 'healthy',
            'backup_enabled' => false,
            'maintenance_mode' => false,
            'auto_ssl_enabled' => false,
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
        ];
    }

    public function nginx(): static
    {
        return $this->state([
            'server_type' => 'nginx',
            'config_path' => '/etc/nginx',
            'sites_path' => '/etc/nginx/sites-available',
            'enabled_path' => '/etc/nginx/sites-enabled',
        ]);
    }

    public function apache(): static
    {
        return $this->state([
            'server_type' => 'apache',
            'config_path' => '/etc/apache2',
            'sites_path' => '/etc/apache2/sites-available',
            'enabled_path' => '/etc/apache2/sites-enabled',
        ]);
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
