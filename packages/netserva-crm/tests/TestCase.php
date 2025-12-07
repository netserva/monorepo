<?php

declare(strict_types=1);

namespace NetServa\Crm\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetServa\Crm\CrmServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CrmServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
