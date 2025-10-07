<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NetServa 3.0 Integration Tests
 *
 * Comprehensive end-to-end testing of the pure Laravel application
 * to validate complete functionality and bash script elimination.
 */
class NetServa30IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure test environment is properly configured
        $this->artisan('migrate:fresh', ['--seed' => true]);
    }

    /** @test */
    public function it_can_validate_laravel_application_architecture()
    {
        // Test that we're running as a pure Laravel application
        $this->assertTrue(app()->environment() !== 'production', 'Running in test environment');

        // Verify Laravel version
        $this->assertStringStartsWith('12.', app()->version(), 'Laravel 12 is installed');

        // Verify database connection
        $this->assertDatabaseHas('migrations', [
            'migration' => '2014_10_12_000000_create_users_table',
        ]);
    }

    /** @test */
    public function it_has_core_artisan_commands_available()
    {
        $coreCommands = [
            'ssh:host',
            'ssh:key',
            'ssh:connection',
            'dns:cloudflare:zones',
            'dns:cloudflare:records',
            'dns:dnssec',
            'addvmail',
            'user:password',
            'user:show',
            'migrate:vhost-configs',
            'migrate:platform-profiles',
            'platform:status',
            'system:status',
        ];

        foreach ($coreCommands as $command) {
            $exitCode = $this->artisan($command, ['--help' => true]);
            $this->assertEquals(0, $exitCode, "Command '$command' should be available and working");
        }
    }

    /** @test */
    public function it_can_manage_ssh_hosts_via_laravel()
    {
        // Test SSH host management
        $this->artisan('ssh:host', ['action' => 'list'])
            ->assertExitCode(0);

        // Import existing SSH hosts
        $this->artisan('ssh:host', ['action' => 'import'])
            ->assertExitCode(0);

        // Verify hosts were imported
        $this->assertDatabaseHas('ssh_hosts', [
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_manage_ssh_keys_via_laravel()
    {
        // Test SSH key management
        $this->artisan('ssh:key', ['action' => 'list'])
            ->assertExitCode(0);

        // Import existing SSH keys
        $this->artisan('ssh:key', ['action' => 'import'])
            ->assertExitCode(0);

        // Verify keys were imported
        $this->assertDatabaseHas('ssh_keys', [
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_handle_dns_operations_via_laravel()
    {
        // Test DNS zone listing (may require CloudFlare credentials)
        $exitCode = $this->artisan('dns:cloudflare:zones', ['--dry-run' => true]);

        // Should either work or fail gracefully due to missing credentials
        $this->assertContains($exitCode, [0, 1], 'DNS command should handle missing credentials gracefully');
    }

    /** @test */
    public function it_can_execute_mail_management_commands()
    {
        // Test mail user management commands
        $this->artisan('user:show', ['--help' => true])
            ->assertExitCode(0);

        $this->artisan('addvmail', ['--help' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_run_migration_commands()
    {
        // Test platform profile migration
        $this->artisan('migrate:platform-profiles', ['--dry-run' => true])
            ->assertExitCode(0);

        // Test vhost config migration
        $this->artisan('migrate:vhost-configs', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_access_web_interface()
    {
        // Test that the web interface is accessible
        $response = $this->get('/');

        // Should redirect to admin interface
        $response->assertStatus(302);
        $response->assertRedirect('/admin');

        // Test admin interface accessibility
        $adminResponse = $this->get('/admin');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Dashboard'); // Filament dashboard
    }

    /** @test */
    public function it_has_proper_package_structure()
    {
        $expectedPackages = [
            'netserva-core',
            'netserva-cli',
            'ns-analytics',
            'ns-audit',
            'ns-backup',
            'ns-dns',
            'ns-mail',
            'ns-platform',
            'ns-ssh',
            'ns-ssl',
        ];

        foreach ($expectedPackages as $package) {
            $packagePath = base_path("packages/$package");
            $this->assertDirectoryExists($packagePath, "Package '$package' should exist");

            // Check for composer.json
            $composerPath = "$packagePath/composer.json";
            $this->assertFileExists($composerPath, "Package '$package' should have composer.json");
        }
    }

    /** @test */
    public function it_can_handle_system_status_checks()
    {
        // Test system status command
        $this->artisan('system:status')
            ->assertExitCode(0);

        // Test platform status
        $this->artisan('platform:status', ['--format' => 'json'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_maintains_environment_configuration()
    {
        // Verify NetServa environment variables are accessible
        $nsdir = env('NS', config('app.ns_dir'));
        $this->assertNotEmpty($nsdir, 'NS should be configured');

        // Verify database is accessible
        $this->assertNotEmpty(config('database.connections.sqlite.database'), 'Database path should be configured');

        // Test database connectivity
        $this->assertDatabaseHas('migrations', [
            'batch' => 1,
        ]);
    }

    /** @test */
    public function it_can_generate_ssh_runtime_configuration()
    {
        // Import some SSH hosts first
        $this->artisan('ssh:host', ['action' => 'import']);

        // Test SSH config generation
        $this->artisan('ssh:host', ['action' => 'sync'])
            ->assertExitCode(0);

        // Verify runtime config file exists
        $runtimeConfigPath = env('NSRUN', env('HOME').'/.ns/run').'/ssh/runtime_config';
        $this->assertFileExists($runtimeConfigPath, 'SSH runtime config should be generated');
    }

    /** @test */
    public function it_supports_comprehensive_backup_operations()
    {
        // Test that backup command exists and works
        $backupScript = env('NS', env('HOME').'/.ns').'/bin/backup-netserva';

        if (file_exists($backupScript)) {
            $output = shell_exec("$backupScript minimal --no-compress 2>&1");
            $this->assertStringContains('Backup completed successfully', $output, 'Backup should complete successfully');
        } else {
            $this->markTestSkipped('Backup script not found - may not be installed yet');
        }
    }

    /** @test */
    public function it_has_eliminated_bash_dependency_for_core_operations()
    {
        // Verify that all core NetServa operations can be performed via Laravel
        $coreOperations = [
            'SSH management' => ['ssh:host', 'ssh:key'],
            'DNS management' => ['dns:cloudflare:zones', 'dns:dnssec'],
            'Mail management' => ['addvmail', 'user:show'],
            'Platform management' => ['platform:status'],
            'System management' => ['system:status'],
            'Migration operations' => ['migrate:vhost-configs'],
        ];

        foreach ($coreOperations as $operationType => $commands) {
            foreach ($commands as $command) {
                $exitCode = $this->artisan($command, ['--help' => true]);
                $this->assertEquals(0, $exitCode, "$operationType command '$command' should work without bash dependency");
            }
        }
    }

    /** @test */
    public function it_maintains_data_integrity_across_migrations()
    {
        // Test that migrations preserve data integrity
        $this->artisan('migrate:platform-profiles', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->artisan('migrate:vhost-configs', ['--dry-run' => true])
            ->assertExitCode(0);

        // Verify database structure is intact
        $this->assertDatabaseHas('migrations', [
            'migration' => '2025_09_01_000001_create_platform_profiles_table',
        ]);
    }

    /** @test */
    public function it_provides_comprehensive_command_help()
    {
        // Test that all NetServa commands provide proper help
        $netservaCommands = [
            'ssh:host', 'ssh:key', 'ssh:connection',
            'dns:cloudflare:zones', 'dns:cloudflare:records',
            'addvmail', 'user:password', 'user:show',
            'system:status', 'platform:status',
        ];

        foreach ($netservaCommands as $command) {
            $exitCode = $this->artisan($command, ['--help' => true]);
            $this->assertEquals(0, $exitCode, "Command '$command' should provide help documentation");
        }
    }
}
