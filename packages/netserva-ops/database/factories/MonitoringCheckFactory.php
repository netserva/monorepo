<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\MonitoringCheck;

class MonitoringCheckFactory extends Factory
{
    protected $model = MonitoringCheck::class;

    public function definition(): array
    {
        $checkType = $this->faker->randomElement(['http', 'tcp', 'ssl', 'dns', 'ping', 'service', 'custom']);

        return [
            // Simple clean schema matching migration and model
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'infrastructure_node_id' => null, // Don't create foreign key references in tests
            'check_type' => $checkType,
            'target' => $this->getTargetForCheckType($checkType),
            'check_config' => $this->getConfigForCheckType($checkType),
            'is_active' => $this->faker->boolean(80),
            'check_interval_seconds' => $this->faker->randomElement([30, 60, 300, 900]),
            'timeout_seconds' => $this->faker->numberBetween(5, 60),
            'status' => $this->faker->randomElement(['up', 'degraded', 'down', 'maintenance']),
            'last_check_message' => $this->faker->optional()->sentence(),
            'last_response_time_ms' => $this->faker->optional()->numberBetween(50, 5000),
            'uptime_percentage' => $this->faker->randomFloat(2, 90, 100),
            'alert_enabled' => $this->faker->boolean(80),
            'alert_contacts' => json_encode([]),
            'in_maintenance' => $this->faker->boolean(10),
            'last_check_at' => $this->faker->optional()->dateTimeBetween('-1 hour', 'now'),
            'next_check_at' => $this->faker->optional()->dateTimeBetween('now', '+1 hour'),
        ];
    }

    protected function getTargetForCheckType(string $checkType): string
    {
        return match ($checkType) {
            'http' => $this->faker->url(),
            'tcp' => $this->faker->domainName().':'.$this->faker->numberBetween(80, 65535),
            'ssl' => $this->faker->domainName(),
            'dns' => $this->faker->domainName(),
            'ping' => $this->faker->domainName(),
            'service' => $this->faker->word(),
            'custom' => $this->faker->domainName(),
            default => $this->faker->url(),
        };
    }

    protected function getConfigForCheckType(string $checkType): array
    {
        return match ($checkType) {
            'http' => [
                'url' => $this->faker->url(),
                'method' => 'GET',
                'expected_status' => 200,
                'timeout' => 30,
            ],
            'tcp' => [
                'host' => $this->faker->domainName(),
                'port' => $this->faker->numberBetween(80, 65535),
                'timeout' => 10,
            ],
            'ssl' => [
                'domain' => $this->faker->domainName(),
                'port' => 443,
                'warning_days' => 30,
                'critical_days' => 7,
            ],
            'dns' => [
                'domain' => $this->faker->domainName(),
                'record_type' => 'A',
                'expected_value' => $this->faker->ipv4(),
                'dns_server' => '8.8.8.8',
            ],
            'ping' => [
                'host' => $this->faker->domainName(),
                'count' => 4,
                'timeout' => 5,
            ],
            'service' => [
                'service_name' => $this->faker->word(),
                'expected_state' => 'running',
            ],
            'custom' => [
                'script' => 'echo "test"',
                'interpreter' => 'bash',
            ],
            default => [],
        };
    }
}
