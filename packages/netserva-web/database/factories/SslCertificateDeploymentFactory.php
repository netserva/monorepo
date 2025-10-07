<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Web\Models\SslCertificate;
use NetServa\Web\Models\SslCertificateDeployment;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Web\Models\SslCertificateDeployment>
 */
class SslCertificateDeploymentFactory extends Factory
{
    protected $model = SslCertificateDeployment::class;

    public function definition(): array
    {
        $serviceType = $this->faker->randomElement(['nginx', 'apache', 'postfix', 'dovecot', 'haproxy']);
        $hostname = $this->faker->domainName();

        $deploymentStarted = $this->faker->dateTimeBetween('-1 month', 'now');
        $deploymentCompleted = $this->faker->boolean(80) ?
            $this->faker->dateTimeBetween($deploymentStarted, 'now') : null;

        return [
            // Core deployment info
            'ssl_certificate_id' => SslCertificate::factory(),
            'infrastructure_node_id' => InfrastructureNode::factory(),
            'server_hostname' => $hostname,
            'service_type' => $serviceType,

            // File paths
            'certificate_path' => "/etc/ssl/certs/{$hostname}.crt",
            'private_key_path' => "/etc/ssl/private/{$hostname}.key",
            'certificate_chain_path' => $this->faker->boolean(70) ?
                "/etc/ssl/certs/{$hostname}-chain.crt" : null,

            // Deployment tracking
            'deployment_type' => $this->faker->randomElement(['manual', 'automated', 'acme', 'api']),
            'status' => $this->faker->randomElement(['pending', 'deploying', 'deployed', 'failed', 'rollback']),
            'deployment_started_at' => $deploymentStarted,
            'deployment_completed_at' => $deploymentCompleted,

            // Configuration
            'deployment_config' => $this->generateDeploymentConfig($serviceType),
            'deployment_notes' => $this->faker->boolean(40) ? $this->faker->sentence() : null,

            // Error tracking
            'deployment_errors' => $this->faker->boolean(20) ? [
                'error_code' => 'DEPLOY_001',
                'error_message' => 'Failed to restart service',
                'timestamp' => now()->toISOString(),
            ] : null,

            // Audit
            'deployed_by' => 'system',
        ];
    }

    public function deployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deployed',
            'deployment_completed_at' => now(),
            'deployment_errors' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'deployment_completed_at' => null,
            'deployment_errors' => [
                'error_code' => 'DEPLOY_ERROR',
                'error_message' => 'Service restart failed',
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    public function nginx(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'nginx',
            'certificate_path' => '/etc/nginx/ssl/'.$this->faker->domainName().'.crt',
            'private_key_path' => '/etc/nginx/ssl/'.$this->faker->domainName().'.key',
            'deployment_config' => [
                'service_name' => 'nginx',
                'reload_command' => 'nginx -s reload',
                'test_command' => 'nginx -t',
                'config_path' => '/etc/nginx/sites-available/',
            ],
        ]);
    }

    public function apache(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'apache',
            'certificate_path' => '/etc/apache2/ssl/'.$this->faker->domainName().'.crt',
            'private_key_path' => '/etc/apache2/ssl/'.$this->faker->domainName().'.key',
            'deployment_config' => [
                'service_name' => 'apache2',
                'reload_command' => 'systemctl reload apache2',
                'test_command' => 'apache2ctl configtest',
                'config_path' => '/etc/apache2/sites-available/',
            ],
        ]);
    }

    public function postfix(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'postfix',
            'certificate_path' => '/etc/postfix/ssl/'.$this->faker->domainName().'.crt',
            'private_key_path' => '/etc/postfix/ssl/'.$this->faker->domainName().'.key',
            'deployment_config' => [
                'service_name' => 'postfix',
                'reload_command' => 'systemctl reload postfix',
                'config_path' => '/etc/postfix/main.cf',
            ],
        ]);
    }

    public function automated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deployment_type' => 'automated',
            'status' => 'deployed',
            'deployed_by' => 'acme-client',
        ]);
    }

    private function generateDeploymentConfig(string $serviceType): array
    {
        $baseConfig = [
            'service_name' => $serviceType,
            'auto_reload' => $this->faker->boolean(80),
            'backup_old_certs' => true,
            'verify_after_deployment' => true,
        ];

        return match ($serviceType) {
            'nginx' => array_merge($baseConfig, [
                'reload_command' => 'nginx -s reload',
                'test_command' => 'nginx -t',
                'config_path' => '/etc/nginx/sites-available/',
            ]),
            'apache' => array_merge($baseConfig, [
                'reload_command' => 'systemctl reload apache2',
                'test_command' => 'apache2ctl configtest',
                'config_path' => '/etc/apache2/sites-available/',
            ]),
            'postfix' => array_merge($baseConfig, [
                'reload_command' => 'systemctl reload postfix',
                'config_path' => '/etc/postfix/main.cf',
            ]),
            'dovecot' => array_merge($baseConfig, [
                'reload_command' => 'systemctl reload dovecot',
                'config_path' => '/etc/dovecot/conf.d/',
            ]),
            'haproxy' => array_merge($baseConfig, [
                'reload_command' => 'systemctl reload haproxy',
                'test_command' => 'haproxy -c -f /etc/haproxy/haproxy.cfg',
                'config_path' => '/etc/haproxy/haproxy.cfg',
            ]),
            default => $baseConfig,
        };
    }
}
