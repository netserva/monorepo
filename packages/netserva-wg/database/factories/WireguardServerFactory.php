<?php

namespace NetServa\Wg\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Wg\Models\WireguardServer;

class WireguardServerFactory extends Factory
{
    protected $model = WireguardServer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company().' WireGuard Server',
            'description' => $this->faker->sentence(),
            'network_cidr' => '10.'.$this->faker->numberBetween(0, 255).'.0.0/24',
            'server_ip' => '10.'.$this->faker->numberBetween(0, 255).'.0.1',
            'listen_port' => $this->faker->numberBetween(51820, 51900),
            'public_key' => base64_encode(random_bytes(32)),
            'private_key_encrypted' => encrypt(base64_encode(random_bytes(32))),
            'endpoint' => $this->faker->ipv4().':'.$this->faker->numberBetween(51820, 51900),
            'ssh_host_id' => null,
            'status' => $this->faker->randomElement(['draft', 'active', 'maintenance', 'error']),
            'is_active' => $this->faker->boolean(),
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'is_active' => true,
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'is_active' => false,
            ];
        });
    }
}
