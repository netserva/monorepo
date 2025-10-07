<?php

declare(strict_types=1);

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\DatabaseConnection;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Database\Models\DatabaseConnection>
 */
class DatabaseConnectionFactory extends Factory
{
    protected $model = DatabaseConnection::class;

    public function definition(): array
    {
        $engine = $this->faker->randomElement(['mysql', 'postgresql', 'sqlite']);
        $uniqueId = $this->faker->unique()->randomNumber(5);

        $port = match ($engine) {
            'postgresql' => $this->faker->randomElement([5432, 5433, 5434]) + ($uniqueId % 10),
            'mysql' => $this->faker->randomElement([3306, 3307, 3308]) + ($uniqueId % 10),
            'sqlite' => 0, // SQLite doesn't use ports
            default => 3306 + ($uniqueId % 10)
        };

        return [
            'name' => $this->faker->domainWord().'_'.$engine.'_'.$uniqueId,
            'host' => $this->faker->randomElement(['localhost', '127.0.0.1', $this->faker->localIpv4()]),
            'port' => $port,
            'engine' => $engine,
            'username' => $this->faker->userName().'_'.$uniqueId,
            'password' => $this->faker->password(),
            'ssl_enabled' => $this->faker->boolean(30),
            'ssl_cert_path' => $this->faker->optional(0.3)->filePath(),
            'is_active' => $this->faker->boolean(85),
        ];
    }

    /**
     * Active connection state
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive connection state
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * MySQL connection
     */
    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine' => 'mysql',
            'port' => $this->faker->randomElement([3306, 3307, 3308]),
        ]);
    }

    /**
     * PostgreSQL connection
     */
    public function postgresql(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine' => 'postgresql',
            'port' => $this->faker->randomElement([5432, 5433, 5434]),
        ]);
    }

    /**
     * SQLite connection
     */
    public function sqlite(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine' => 'sqlite',
            'host' => ':memory:',
            'port' => 0,
        ]);
    }

    /**
     * SSL enabled connection
     */
    public function withSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => true,
            'ssl_cert_path' => '/etc/ssl/certs/db.pem',
        ]);
    }
}
