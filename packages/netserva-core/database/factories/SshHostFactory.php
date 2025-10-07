<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\Server;
use NetServa\Core\Models\SshHost;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\SshHost>
 */
class SshHostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = SshHost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hostname = fake()->unique()->domainName();

        return [
            'host' => fake()->unique()->userName(),
            'hostname' => $hostname,
            'port' => 22,
            'user' => fake()->randomElement(['root', 'admin', 'deploy', fake()->userName()]),
            'identity_file' => '~/.ssh/id_'.fake()->randomElement(['rsa', 'ed25519', 'ecdsa']),
            'proxy_command' => null,
            'jump_host' => null,
            'custom_options' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
            'last_tested_at' => null,
            'is_reachable' => null,
            'last_error' => null,
        ];
    }

    /**
     * Indicate that the host is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the host is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the host is reachable.
     */
    public function reachable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reachable' => true,
            'last_tested_at' => now(),
        ]);
    }

    /**
     * Indicate that the host is unreachable.
     */
    public function unreachable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reachable' => false,
            'last_tested_at' => now(),
            'last_error' => 'Connection timed out',
        ]);
    }

    /**
     * Configure the host to use a jump host.
     */
    public function withJumpHost(string $jumpHost): static
    {
        return $this->state(fn (array $attributes) => [
            'jump_host' => $jumpHost,
        ]);
    }

    /**
     * Configure the host with a proxy command.
     */
    public function withProxyCommand(string $command): static
    {
        return $this->state(fn (array $attributes) => [
            'proxy_command' => $command,
        ]);
    }

    /**
     * Configure the host with custom SSH options.
     */
    public function withCustomOptions(array $options): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_options' => $options,
        ]);
    }

    /**
     * Configure the host with a specific identity file.
     */
    public function withIdentityFile(string $path): static
    {
        return $this->state(fn (array $attributes) => [
            'identity_file' => $path,
        ]);
    }

    /**
     * Configure the host with a specific port.
     */
    public function onPort(int $port): static
    {
        return $this->state(fn (array $attributes) => [
            'port' => $port,
        ]);
    }

    /**
     * Configure the host with a specific user.
     */
    public function asUser(string $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user' => $user,
        ]);
    }

    /**
     * Associate the SSH host with a server.
     */
    public function forServer(Server $server): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $server->id,
        ]);
    }
}
