<?php

namespace NetServa\Wg\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Wg\Models\WireguardPeer;
use NetServa\Wg\Models\WireguardServer;

class WireguardPeerFactory extends Factory
{
    protected $model = WireguardPeer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->firstName().' '.$this->faker->lastName(),
            'wireguard_server_id' => WireguardServer::factory(),
            'allocated_ip' => '10.'.$this->faker->numberBetween(0, 255).'.0.'.$this->faker->numberBetween(2, 254),
            'allowed_ips' => ['0.0.0.0/0', '::/0'],
            'public_key' => base64_encode(random_bytes(32)),
            'private_key_encrypted' => encrypt(base64_encode(random_bytes(32))),
            'status' => $this->faker->randomElement(['disconnected', 'connected', 'error']),
            'last_handshake' => $this->faker->optional()->dateTimeBetween('-1 hour', 'now'),
            'is_active' => $this->faker->boolean(),
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'connected',
                'is_active' => true,
                'last_handshake' => now()->subMinutes($this->faker->numberBetween(1, 60)),
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'disconnected',
                'is_active' => false,
                'last_handshake' => null,
            ];
        });
    }
}
