<?php

use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Rollback VHost Command Tests
 *
 * Tests the CLI command for rolling back migrated vhosts
 */
describe('RollbackVhostCommand', function () {
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

    it('requires vnode and vhost arguments', function () {
        $this->artisan('rollback:vhost')
            ->assertExitCode(1);
    });

    it('fails when vnode is not found', function () {
        $this->artisan('rollback:vhost non-existent example.com')
            ->expectsOutput('❌ VNode not found: non-existent')
            ->assertExitCode(1);
    });

    it('fails when vhost is not found', function () {
        $this->artisan("rollback:vhost {$this->vnode->name} non-existent.com")
            ->expectsOutput('❌ VHost not found: non-existent.com')
            ->assertExitCode(1);
    });

    it('lists available rollback points', function () {
        $vhost = FleetVhost::create([
            'domain' => 'list-rollbacks.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true,
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/list-rollbacks.test',
        ]);

        // Mock the migration service
        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('listRollbackPoints')
            ->once()
            ->andReturn([
                'success' => true,
                'rollback_points' => [
                    [
                        'filename' => 'pre-migration-20251009-120000.tar.gz',
                        'path' => '/srv/list-rollbacks.test/.archive/pre-migration-20251009-120000.tar.gz',
                        'created_at' => '2025-10-09 12:00:00',
                    ],
                    [
                        'filename' => 'pre-migration-20251008-100000.tar.gz',
                        'path' => '/srv/list-rollbacks.test/.archive/pre-migration-20251008-100000.tar.gz',
                        'created_at' => '2025-10-08 10:00:00',
                    ],
                ],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --list")
            ->expectsOutput('📋 Available Rollback Points for list-rollbacks.test')
            ->expectsOutputToContain('pre-migration-20251009-120000.tar.gz')
            ->expectsOutputToContain('pre-migration-20251008-100000.tar.gz')
            ->expectsOutputToContain('2025-10-09 12:00:00')
            ->assertExitCode(0);
    });

    it('shows message when no rollback points exist', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-rollbacks.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/no-rollbacks.test',
        ]);

        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('listRollbackPoints')
            ->once()
            ->andReturn([
                'success' => true,
                'rollback_points' => [],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --list")
            ->expectsOutput('No rollback points found.')
            ->expectsOutputToContain('Rollback archives are created in .archive/ directory')
            ->assertExitCode(0);
    });

    it('fails rollback when rollback is not available', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-rollback-available.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => false, // No rollback
        ]);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('❌ No rollback available for this vhost')
            ->expectsOutputToContain('VHost was migrated with --no-backup flag')
            ->assertExitCode(1);
    });

    it('successfully rolls back a migrated vhost', function () {
        $vhost = FleetVhost::create([
            'domain' => 'rollback.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true,
            'migration_backup_path' => '/srv/rollback.test/.archive/pre-migration-20251009.tar.gz',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'rollback.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'UPATH' => '/srv/rollback.test',
            'WUGID' => '33',
        ]);

        // Mock successful rollback
        $mockService = Mockery::mock(MigrationExecutionService::class);

        // listRollbackPoints called first
        $mockService->shouldReceive('listRollbackPoints')
            ->once()
            ->andReturn([
                'success' => true,
                'rollback_points' => [
                    [
                        'filename' => 'pre-migration-20251009.tar.gz',
                        'path' => '/srv/rollback.test/.archive/pre-migration-20251009.tar.gz',
                        'created_at' => '2025-10-09 12:00:00',
                    ],
                ],
            ]);

        // rollbackVhost called second
        $mockService->shouldReceive('rollbackVhost')
            ->once()
            ->with(
                Mockery::on(fn ($v) => $v->domain === 'rollback.test'),
                '/srv/rollback.test/.archive/pre-migration-20251009.tar.gz'
            )
            ->andReturn([
                'success' => true,
                'vhost' => 'rollback.test',
                'archive_restored' => '/srv/rollback.test/.archive/pre-migration-20251009.tar.gz',
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('🔄 Rollback VHost: rollback.test on test-vnode')
            ->expectsOutput('🚀 Starting rollback...')
            ->expectsOutput('✅ Rollback completed successfully!')
            ->expectsOutputToContain('Status: validated (ready for re-migration)')
            ->expectsOutputToContain('Archive Restored:')
            ->assertExitCode(0);
    });

    it('displays rollback failure with error message', function () {
        $vhost = FleetVhost::create([
            'domain' => 'fail-rollback.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true,
            'migration_backup_path' => '/srv/fail-rollback.test/.archive/pre-migration.tar.gz',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'fail-rollback.test',
            'UPATH' => '/srv/fail-rollback.test',
            'WUGID' => '33',
        ]);

        $mockService = Mockery::mock(MigrationExecutionService::class);

        $mockService->shouldReceive('listRollbackPoints')
            ->once()
            ->andReturn([
                'success' => true,
                'rollback_points' => [
                    [
                        'filename' => 'pre-migration.tar.gz',
                        'path' => '/srv/fail-rollback.test/.archive/pre-migration.tar.gz',
                        'created_at' => '2025-10-09 12:00:00',
                    ],
                ],
            ]);

        $mockService->shouldReceive('rollbackVhost')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Archive file corrupted',
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('❌ Rollback failed: Archive file corrupted')
            ->assertExitCode(1);
    });

    it('shows warning about SSH directory restoration', function () {
        $vhost = FleetVhost::create([
            'domain' => 'warning.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true,
            'migration_backup_path' => '/srv/warning.test/.archive/pre-migration.tar.gz',
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/warning.test',
            'WUGID' => '33',
        ]);

        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('listRollbackPoints')
            ->andReturn([
                'success' => true,
                'rollback_points' => [
                    ['filename' => 'pre-migration.tar.gz', 'path' => '/srv/warning.test/.archive/pre-migration.tar.gz', 'created_at' => '2025-10-09'],
                ],
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --dry-run")
            ->expectsOutputToContain('⚠️  Warning: This will restore SSH directories (.ssh, bin, etc)')
            ->expectsOutputToContain('Current web-centric structure will be modified.')
            ->assertExitCode(0);
    });

    it('fails when no backup archives are found despite rollback_available flag', function () {
        $vhost = FleetVhost::create([
            'domain' => 'missing-archives.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true, // Flag says yes
            'migration_backup_path' => '/srv/missing-archives.test/.archive/pre-migration.tar.gz',
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/missing-archives.test',
            'WUGID' => '33',
        ]);

        $mockService = Mockery::mock(MigrationExecutionService::class);
        $mockService->shouldReceive('listRollbackPoints')
            ->once()
            ->andReturn([
                'success' => true,
                'rollback_points' => [], // But no archives exist
            ]);

        $this->app->instance(MigrationExecutionService::class, $mockService);

        $this->artisan("rollback:vhost {$this->vnode->name} {$vhost->domain} --force")
            ->expectsOutput('❌ No backup archives found')
            ->assertExitCode(1);
    });

    afterEach(function () {
        Mockery::close();
    });
});
