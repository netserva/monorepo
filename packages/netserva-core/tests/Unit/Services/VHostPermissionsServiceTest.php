<?php

namespace NetServa\Core\Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use Mockery;
use NetServa\Core\Models\VHost;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\VhostConfigService;
use NetServa\Core\Services\VHostPermissionsService;
use Tests\TestCase;

class VHostPermissionsServiceTest extends TestCase
{
    protected VHostPermissionsService $service;

    protected $remoteExecutionService;

    protected $vhostConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        Log::shouldReceive('info', 'error', 'debug')->byDefault();

        $this->remoteExecutionService = Mockery::mock(RemoteExecutionService::class);
        $this->vhostConfigService = Mockery::mock(VhostConfigService::class);

        $this->service = new VHostPermissionsService(
            $this->remoteExecutionService,
            $this->vhostConfigService
        );
    }

    /** @test */
    public function it_can_fix_vhost_permissions_successfully()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $this->remoteExecutionService->shouldReceive('executeAsRoot')
            ->times(15) // Approximate number of commands
            ->andReturn(['success' => true]);

        $result = $this->service->fixVHostPermissions('motd', 'example.com');

        expect($result['success'])->toBeTrue();
        expect($result['vhost'])->toBe('example.com');
        expect($result['vnode'])->toBe('motd');
        expect($result['commands_executed'])->toBeGreaterThan(0);
        expect($result['details'])->toBeArray();
    }

    /** @test */
    public function it_handles_missing_vhost_configuration()
    {
        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'nonexistent.com')
            ->once()
            ->andReturn(null);

        $result = $this->service->fixVHostPermissions('motd', 'nonexistent.com');

        expect($result['success'])->toBeFalse();
        expect($result['vhost'])->toBe('nonexistent.com');
        expect($result['vnode'])->toBe('motd');
        expect($result['error'])->toContain('VHost configuration not found');
    }

    /** @test */
    public function it_returns_dry_run_commands_when_dry_run_enabled()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $result = $this->service->fixVHostPermissions('motd', 'example.com', ['dry_run' => true]);

        expect($result['success'])->toBeTrue();
        expect($result['dry_run'])->toBeTrue();
        expect($result['commands'])->toBeArray();
        expect($result['commands'])->not->toBeEmpty();
        expect($result['commands'][0])->toContain('chown -R u1001:www-data /srv/example.com');
    }

    /** @test */
    public function it_respects_web_only_option()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $result = $this->service->fixVHostPermissions('motd', 'example.com', [
            'dry_run' => true,
            'web_only' => true,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['commands'])->toBeArray();

        // Should contain web-related commands but not mail-related
        $commandsString = implode(' ', $result['commands']);
        expect($commandsString)->toContain('/srv/example.com/var/www');
        expect($commandsString)->not->toContain('/srv/example.com/msg');
    }

    /** @test */
    public function it_respects_mail_only_option()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $result = $this->service->fixVHostPermissions('motd', 'example.com', [
            'dry_run' => true,
            'mail_only' => true,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['commands'])->toBeArray();

        // Should contain mail-related commands but not web-related find commands
        $commandsString = implode(' ', $result['commands']);
        expect($commandsString)->toContain('/srv/example.com/msg');
        expect($commandsString)->not->toContain('find /srv/example.com/var/www');
    }

    /** @test */
    public function it_can_fix_multiple_vhost_permissions()
    {
        $vhosts = ['example1.com', 'example2.com', 'example3.com'];

        foreach ($vhosts as $vhost) {
            $config = [
                'VHOST' => $vhost,
                'UUSER' => 'u1001',
                'WUGID' => 'www-data',
                'UPATH' => "/srv/{$vhost}",
                'WPATH' => "/srv/{$vhost}/var/www",
                'MPATH' => "/srv/{$vhost}/msg",
            ];

            $this->vhostConfigService->shouldReceive('loadVhostConfig')
                ->with('motd', $vhost)
                ->once()
                ->andReturn($config);

            $this->remoteExecutionService->shouldReceive('executeAsRoot')
                ->andReturn(['success' => true]);
        }

        $result = $this->service->fixMultipleVHostPermissions('motd', $vhosts);

        expect($result['success'])->toBeTrue();
        expect($result['total_processed'])->toBe(3);
        expect($result['total_success'])->toBe(3);
        expect($result['total_errors'])->toBe(0);
        expect($result['results'])->toHaveCount(3);
    }

    /** @test */
    public function it_handles_mixed_success_and_failure_in_multiple_vhosts()
    {
        $vhosts = ['example1.com', 'nonexistent.com', 'example3.com'];

        // First vhost - success
        $config1 = [
            'VHOST' => 'example1.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example1.com',
            'WPATH' => '/srv/example1.com/var/www',
            'MPATH' => '/srv/example1.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example1.com')
            ->once()
            ->andReturn($config1);

        $this->remoteExecutionService->shouldReceive('executeAsRoot')
            ->andReturn(['success' => true]);

        // Second vhost - failure (no config)
        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'nonexistent.com')
            ->once()
            ->andReturn(null);

        // Third vhost - success
        $config3 = [
            'VHOST' => 'example3.com',
            'UUSER' => 'u1003',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example3.com',
            'WPATH' => '/srv/example3.com/var/www',
            'MPATH' => '/srv/example3.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example3.com')
            ->once()
            ->andReturn($config3);

        $this->remoteExecutionService->shouldReceive('executeAsRoot')
            ->andReturn(['success' => true]);

        $result = $this->service->fixMultipleVHostPermissions('motd', $vhosts);

        expect($result['success'])->toBeFalse(); // Overall failure due to one failure
        expect($result['total_processed'])->toBe(3);
        expect($result['total_success'])->toBe(2);
        expect($result['total_errors'])->toBe(1);
        expect($result['results'])->toHaveCount(3);
    }

    /** @test */
    public function it_can_fix_all_vhost_permissions()
    {
        $vhosts = ['example1.com', 'example2.com'];

        $this->vhostConfigService->shouldReceive('getVhostsForServer')
            ->with('motd')
            ->once()
            ->andReturn($vhosts);

        foreach ($vhosts as $vhost) {
            $config = [
                'VHOST' => $vhost,
                'UUSER' => 'u1001',
                'WUGID' => 'www-data',
                'UPATH' => "/srv/{$vhost}",
                'WPATH' => "/srv/{$vhost}/var/www",
                'MPATH' => "/srv/{$vhost}/msg",
            ];

            $this->vhostConfigService->shouldReceive('loadVhostConfig')
                ->with('motd', $vhost)
                ->once()
                ->andReturn($config);

            $this->remoteExecutionService->shouldReceive('executeAsRoot')
                ->andReturn(['success' => true]);
        }

        $result = $this->service->fixAllVHostPermissions('motd');

        expect($result['success'])->toBeTrue();
        expect($result['total_processed'])->toBe(2);
        expect($result['total_success'])->toBe(2);
        expect($result['total_errors'])->toBe(0);
    }

    /** @test */
    public function it_handles_no_vhosts_found_gracefully()
    {
        $this->vhostConfigService->shouldReceive('getVhostsForServer')
            ->with('motd')
            ->once()
            ->andReturn([]);

        $result = $this->service->fixAllVHostPermissions('motd');

        expect($result['success'])->toBeTrue();
        expect($result['total_processed'])->toBe(0);
        expect($result['message'])->toContain('No virtual hosts found');
    }

    /** @test */
    public function it_can_get_vhost_paths()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $paths = $this->service->getVHostPaths('motd', 'example.com');

        expect($paths)->toHaveCount(3);
        expect($paths[0]['type'])->toBe('User Home');
        expect($paths[0]['path'])->toBe('/srv/example.com');
        expect($paths[0]['owner'])->toBe('u1001:www-data');
        expect($paths[1]['type'])->toBe('Web Root');
        expect($paths[1]['path'])->toBe('/srv/example.com/var/www');
        expect($paths[2]['type'])->toBe('Mail Root');
        expect($paths[2]['path'])->toBe('/srv/example.com/msg');
    }

    /** @test */
    public function it_can_validate_vhost_exists()
    {
        $this->vhostConfigService->shouldReceive('exists')
            ->with('motd/example.com')
            ->once()
            ->andReturn(true);

        $exists = $this->service->validateVHostExists('motd', 'example.com');

        expect($exists)->toBeTrue();
    }

    /** @test */
    public function it_can_get_available_vhosts()
    {
        $vhosts = ['example1.com', 'example2.com', 'example3.com'];

        $this->vhostConfigService->shouldReceive('getVhostsForServer')
            ->with('motd')
            ->once()
            ->andReturn($vhosts);

        $result = $this->service->getAvailableVHosts('motd');

        expect($result)->toBe($vhosts);
    }

    /** @test */
    public function it_generates_correct_permission_commands()
    {
        $config = [
            'VHOST' => 'example.com',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/var/www',
            'MPATH' => '/srv/example.com/msg',
        ];

        $this->vhostConfigService->shouldReceive('loadVhostConfig')
            ->with('motd', 'example.com')
            ->once()
            ->andReturn($config);

        $result = $this->service->fixVHostPermissions('motd', 'example.com', ['dry_run' => true]);

        $commands = $result['commands'];
        $commandsString = implode("\n", $commands);

        // Check that essential commands are present
        expect($commandsString)->toContain('chown -R u1001:www-data /srv/example.com');
        expect($commandsString)->toContain('chmod 755 /srv/example.com');
        expect($commandsString)->toContain('find /srv/example.com/var/www -type d -exec chmod 755');
        expect($commandsString)->toContain('find /srv/example.com/var/www -type f -exec chmod 644');
        expect($commandsString)->toContain('chown -R u1001:www-data /srv/example.com/msg');
        expect($commandsString)->toContain('find /srv/example.com/msg -type d -exec chmod 750');
        expect($commandsString)->toContain('find /srv/example.com/msg -type f -exec chmod 640');

        // Check SSL certificate commands
        expect($commandsString)->toContain('/etc/ssl/le/example.com');

        // Check log directory commands
        expect($commandsString)->toContain('/var/log/example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
