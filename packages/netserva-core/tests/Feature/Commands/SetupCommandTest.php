<?php

use NetServa\Core\Console\Commands\SetupCommand;
use NetServa\Core\Models\SshHost;
use Ns\Setup\Models\SetupTemplate;
use Ns\Setup\Services\SetupService;

uses()
    ->group('feature', 'commands', 'setup-command', 'priority-1');

beforeEach(function () {
    $this->setupService = $this->mock(SetupService::class);
});

it('can run setup command with list action', function () {
    SetupTemplate::factory()->count(3)->create();

    $this->setupService
        ->shouldReceive('listTemplates')
        ->once()
        ->andReturn(collect([
            ['id' => 1, 'name' => 'basic-web', 'description' => 'Basic web server'],
            ['id' => 2, 'name' => 'mail-server', 'description' => 'Mail server setup'],
            ['id' => 3, 'name' => 'dns-server', 'description' => 'DNS server setup'],
        ]));

    $this->artisan(SetupCommand::class, ['action' => 'list'])
        ->assertExitCode(0)
        ->expectsOutputToContain('Available Setup Templates')
        ->expectsOutputToContain('basic-web')
        ->expectsOutputToContain('mail-server')
        ->expectsOutputToContain('dns-server');
});

it('can run setup command with deploy action', function () {
    $template = SetupTemplate::factory()->create(['name' => 'basic-web']);
    $host = SshHost::factory()->create(['hostname' => 'test.example.com']);

    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->with($template->name, $host->hostname, [], false)
        ->andReturn([
            'success' => true,
            'message' => 'Template deployed successfully',
            'tasks_completed' => 5,
            'duration' => '2.3s',
        ]);

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'test.example.com',
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Template deployed successfully')
        ->expectsOutputToContain('5 tasks completed');
});

it('can run setup command with status action', function () {
    $host = SshHost::factory()->create(['hostname' => 'test.example.com']);

    $this->setupService
        ->shouldReceive('getSetupStatus')
        ->once()
        ->with($host->hostname)
        ->andReturn([
            'hostname' => 'test.example.com',
            'status' => 'configured',
            'templates' => ['basic-web', 'ssl-setup'],
            'last_setup' => '2024-01-15 10:30:00',
            'components' => [
                'nginx' => 'active',
                'php' => 'active',
                'mysql' => 'active',
            ],
        ]);

    $this->artisan(SetupCommand::class, [
        'action' => 'status',
        'host' => 'test.example.com',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Setup Status: test.example.com')
        ->expectsOutputToContain('Status: configured')
        ->expectsOutputToContain('nginx: active');
});

it('can run setup command with seed action', function () {
    $this->setupService
        ->shouldReceive('seedTemplates')
        ->once()
        ->andReturn([
            'templates_created' => 8,
            'components_created' => 25,
            'duration' => '1.2s',
        ]);

    $this->artisan(SetupCommand::class, ['action' => 'seed', '--force' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Seeding setup templates')
        ->expectsOutputToContain('8 templates created')
        ->expectsOutputToContain('25 components created');
});

it('handles dry run option for deploy action', function () {
    $template = SetupTemplate::factory()->create(['name' => 'basic-web']);
    $host = SshHost::factory()->create(['hostname' => 'test.example.com']);

    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->with($template->name, $host->hostname, [], true) // dry_run = true
        ->andReturn([
            'success' => true,
            'message' => 'Dry run completed - would deploy 5 tasks',
            'tasks_would_run' => 5,
            'duration' => '0.1s',
        ]);

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'test.example.com',
        '--dry-run' => true,
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('would deploy 5 tasks');
});

it('handles config overrides via JSON', function () {
    $template = SetupTemplate::factory()->create(['name' => 'basic-web']);
    $host = SshHost::factory()->create(['hostname' => 'test.example.com']);

    $configOverrides = ['php_version' => '8.4', 'enable_ssl' => true];

    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->with($template->name, $host->hostname, $configOverrides, false)
        ->andReturn([
            'success' => true,
            'message' => 'Template deployed with custom config',
            'tasks_completed' => 6,
        ]);

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'test.example.com',
        '--config' => '{"php_version":"8.4","enable_ssl":true}',
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Template deployed with custom config');
});

it('returns error for invalid action', function () {
    $this->artisan(SetupCommand::class, ['action' => 'invalid-action'])
        ->assertExitCode(1)
        ->expectsOutputToContain('Unknown action: invalid-action');
});

it('handles template not found error', function () {
    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->andThrow(new \Exception('Template not found: nonexistent-template'));

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'nonexistent-template',
        'host' => 'test.example.com',
        '--force' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Error: Template not found');
});

it('handles host not found error', function () {
    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->andThrow(new \Exception('SSH host not found: nonexistent.example.com'));

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'nonexistent.example.com',
        '--force' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Error: SSH host not found');
});

it('shows progress during deployment', function () {
    $template = SetupTemplate::factory()->create(['name' => 'basic-web']);
    $host = SshHost::factory()->create(['hostname' => 'test.example.com']);

    $this->setupService
        ->shouldReceive('deployTemplate')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Template deployed successfully',
            'tasks_completed' => 5,
            'duration' => '5.7s',
        ]);

    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'test.example.com',
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Deploying template: basic-web')
        ->expectsOutputToContain('Target host: test.example.com')
        ->expectsOutputToContain('Duration: 5.7s');
});

it('validates JSON config format', function () {
    $this->artisan(SetupCommand::class, [
        'action' => 'deploy',
        'template' => 'basic-web',
        'host' => 'test.example.com',
        '--config' => 'invalid-json',
        '--force' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid JSON configuration');
});

it('can show empty template list', function () {
    $this->setupService
        ->shouldReceive('listTemplates')
        ->once()
        ->andReturn(collect([]));

    $this->artisan(SetupCommand::class, ['action' => 'list'])
        ->assertExitCode(0)
        ->expectsOutputToContain('No setup templates found');
});
