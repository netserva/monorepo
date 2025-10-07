<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Web\Models\VirtualHost;
use NetServa\Web\Models\WebApplication;

class WebApplicationFactory extends Factory
{
    protected $model = WebApplication::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' App',
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'virtual_host_id' => VirtualHost::factory(),
            'application_type' => fake()->randomElement(['laravel', 'wordpress', 'static', 'nodejs']),
            'repository_url' => 'https://github.com/'.fake()->userName().'/'.fake()->slug(),
            'repository_branch' => 'main',
            'current_version' => fake()->semver(),
            'current_environment' => fake()->randomElement(['production', 'staging', 'development']),
            'installation_path' => '/var/www/'.fake()->slug(),
            'installation_status' => 'installed',
            'php_version' => '8.4',
            'nodejs_version' => '20.12.0',
            'build_command' => 'composer install',
            'composer_version' => '2.5.0',
            'database_required' => true,
            'database_type' => fake()->randomElement(['mysql', 'postgresql', 'sqlite']),
            'caching_enabled' => false,
            'monitoring_enabled' => false,
            'backup_enabled' => false,
            'environment_variables' => json_encode([
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
            ]),
            'configuration' => json_encode([]),
            'tags' => json_encode([]),
            'metadata' => json_encode([]),
        ];
    }

    public function laravel(): static
    {
        return $this->state([
            'type' => 'laravel',
            'language' => 'php',
            'runtime_version' => '8.4',
            'framework' => 'laravel',
            'package_manager' => 'composer',
            'build_command' => 'composer install --no-dev --optimize-autoloader',
            'start_command' => 'php artisan serve',
            'database_type' => 'mysql',
        ]);
    }

    public function wordpress(): static
    {
        return $this->state([
            'type' => 'wordpress',
            'language' => 'php',
            'runtime_version' => '8.4',
            'framework' => 'wordpress',
            'database_type' => 'mysql',
        ]);
    }

    public function static(): static
    {
        return $this->state([
            'type' => 'static',
            'language' => 'html',
            'database_enabled' => false,
            'database_type' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(['is_active' => true, 'status' => 'deployed']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false, 'status' => 'inactive']);
    }
}
