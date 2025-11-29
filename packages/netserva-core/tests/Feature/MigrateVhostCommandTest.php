<?php

use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Migrate VHost Command Tests
 *
 * Tests the CLI command for migrating vhosts from NS 1.0 to NS 3.0
 */
describe('MigrateVhostCommand', function () {
    beforeEach(function () {
        // Create test vsite first
        $this->vsite = FleetVsite::create([
            'name' => 'test-site',
            'slug' => 'test-site',
            'environment' => 'testing',
            'provider' => 'local',
            'status' => 'active',
        ]);

        // Create test vnode
        $this->vnode = FleetVnode::create([
            'name' => 'test-vnode',
            'hostname' => 'test.example.com',
            'vsite_id' => $this->vsite->id,
            'role' => 'compute',
            'environment' => 'testing',
            'provider' => 'local',
            'status' => 'active',
        ]);
    });

    it('requires vnode and vhost arguments when not using --all-validated', function () {
        $this->artisan('migrate:vhost')
            ->expectsOutput('Please provide vnode and vhost arguments, or use --all-validated flag')
            ->assertExitCode(1);
    });

    it('fails when vnode is not found', function () {
        $this->artisan('migrate:vhost non-existent example.com')
            ->expectsOutput('❌ VNode not found: non-existent')
            ->assertExitCode(1);
    });

    it('fails when vhost is not found', function () {
        $this->artisan("migrate:vhost {$this->vnode->name} non-existent.com")
            ->expectsOutput('❌ VHost not found: non-existent.com')
            ->assertExitCode(1);
    });

    it('shows dry-run migration plan', function () {
        $vhost = FleetVhost::create([
            'domain' => 'dryrun.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'dryrun.test',
            'UPATH' => '/srv/dryrun.test',
            'WPATH' => '/srv/dryrun.test/web',
        ]);

        $this->artisan("migrate:vhost {$this->vnode->name} {$vhost->domain} --dry-run")
            ->expectsOutput('🔍 Dry-Run Mode: Migration Plan')
            ->expectsOutputToContain('Pre-flight Checks')
            ->expectsOutputToContain('Backup Creation')
            ->expectsOutputToContain('Structural Migration')
            ->expectsOutputToContain('This was a dry-run. No changes were made.')
            ->assertExitCode(0);
    });

    it('shows current migration status', function () {
        $vhost = FleetVhost::create([
            'domain' => 'status.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $this->artisan("migrate:vhost {$this->vnode->name} {$vhost->domain} --dry-run")
            ->expectsOutputToContain('📊 Current Status:')
            ->expectsOutputToContain('Migration Status: validated')
            ->assertExitCode(0);
    });

    it('migrates validated vhost successfully with mocked service', function () {
        $vhost = FleetVhost::create([
            'domain' => 'migrate.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'migrate.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/migrate.test',
            'WPATH' => '/srv/migrate.test/web',
            'MPATH' => '/srv/migrate.test/msg',
            'WUGID' => '33',
        ]);

        // Mock the migration service
        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('migrateVhost')
            ->once()
            ->with(
                Mockery::on(fn ($v) => $v->domain === 'migrate.test'),
                false
            )
            ->andReturn([
                'success' => true,
                'vhost' => 'migrate.test',
                'migration_log' => [
                    'started_at' => '2025-10-09T12:00:00Z',
                    'completed_at' => '2025-10-09T12:05:00Z',
                    'status' => 'completed',
                    'steps_completed' => ['backup_creation', 'structural_migration', 'verification'],
                    'backup_archive' => '/srv/migrate.test/.archive/pre-migration-20251009.tar.gz',
                    'structural_changes' => ['created_web_app_dir', 'moved_var_log'],
                    'warnings' => [],
                    'errors' => [],
                ],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("migrate:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('🚀 Starting migration...')
            ->expectsOutput('✅ Migration completed successfully!')
            ->expectsOutputToContain('Steps Completed: 3')
            ->assertExitCode(0);
    });

    it('displays migration failure with error details', function () {
        $vhost = FleetVhost::create([
            'domain' => 'fail.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'fail.test',
            'UPATH' => '/srv/fail.test',
            'WPATH' => '/srv/fail.test/web',
            'WUGID' => '33',
        ]);

        // Mock failed migration
        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('migrateVhost')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Disk full - cannot create backup',
                'migration_log' => [
                    'status' => 'failed',
                    'steps_completed' => [],
                    'errors' => ['Disk full - cannot create backup'],
                    'warnings' => [],
                ],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("migrate:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('❌ Migration failed: Disk full - cannot create backup')
            ->assertExitCode(1);
    });

    it('migrates all validated vhosts with --all-validated flag', function () {
        // Create multiple validated vhosts
        $vhost1 = FleetVhost::create([
            'domain' => 'batch1.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost2 = FleetVhost::create([
            'domain' => 'batch2.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        // Create a non-validated vhost (should be skipped)
        FleetVhost::create([
            'domain' => 'not-validated.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'discovered',
        ]);

        foreach ([$vhost1, $vhost2] as $vhost) {
            $vhost->setEnvVars([
                'VHOST' => $vhost->domain,
                'UPATH' => "/srv/{$vhost->domain}",
                'WPATH' => "/srv/{$vhost->domain}/web",
                'WUGID' => '33',
            ]);
        }

        // Mock the migration service
        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('migrateVhost')
            ->twice()
            ->andReturn([
                'success' => true,
                'vhost' => 'batch.test',
                'migration_log' => [
                    'status' => 'completed',
                    'steps_completed' => ['backup_creation', 'structural_migration', 'verification'],
                ],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan('migrate:vhost --all-validated --force')
            ->expectsOutput('🔄 Migrating all validated vhosts')
            ->expectsOutput('Found 2 validated vhosts')
            ->expectsOutputToContain('📊 Migration Summary:')
            ->expectsOutputToContain('Total: 2')
            ->expectsOutputToContain('Success: 2')
            ->assertExitCode(0);
    });

    it('handles mixed success/failure in batch migration', function () {
        $vhost1 = FleetVhost::create([
            'domain' => 'success.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost2 = FleetVhost::create([
            'domain' => 'fail.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        foreach ([$vhost1, $vhost2] as $vhost) {
            $vhost->setEnvVars([
                'VHOST' => $vhost->domain,
                'UPATH' => "/srv/{$vhost->domain}",
                'WPATH' => "/srv/{$vhost->domain}/web",
                'WUGID' => '33',
            ]);
        }

        // Mock mixed results
        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('migrateVhost')
            ->once()
            ->andReturn([
                'success' => true,
                'migration_log' => ['status' => 'completed', 'steps_completed' => []],
            ]);

        $mockService->shouldReceive('migrateVhost')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Permission denied',
                'migration_log' => ['status' => 'failed', 'steps_completed' => []],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan('migrate:vhost --all-validated --force')
            ->expectsOutputToContain('Success: 1')
            ->expectsOutputToContain('Failed: 1')
            ->assertExitCode(1); // Non-zero exit code when failures occur
    });

    it('warns when using --no-backup flag', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-backup.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'no-backup.test',
            'UPATH' => '/srv/no-backup.test',
            'WPATH' => '/srv/no-backup.test/web',
            'WUGID' => '33',
        ]);

        $this->artisan("migrate:vhost {$this->vnode->name} {$vhost->domain} --no-backup --dry-run")
            ->expectsOutputToContain('⚠️  SKIPPED')
            ->assertExitCode(0);
    });

    afterEach(function () {
        Mockery::close();
    });
});
