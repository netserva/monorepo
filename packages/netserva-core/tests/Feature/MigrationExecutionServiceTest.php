<?php

use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Migration Execution Service Tests
 *
 * Tests the migration execution service for NetServa 1.0 → 3.0 migration
 */
describe('MigrationExecutionService', function () {
    beforeEach(function () {
        // Create mock remote execution service
        $this->mockRemoteExecution = Mockery::mock(RemoteExecutionService::class);
        $this->migrationService = new MigrationExecutionService($this->mockRemoteExecution);

        // Create test vsite first (required for vnode)
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

    afterEach(function () {
        Mockery::close();
    });

    it('fails migration when vhost has no vnode', function () {
        $vhost = FleetVhost::create([
            'domain' => 'orphan.test',
            'vnode_id' => null, // No vnode
            'provider' => 'local',
            'status' => 'active',
        ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('no associated VNode');
    });

    it('fails migration when vhost is already migrated', function () {
        $vhost = FleetVhost::create([
            'domain' => 'already-migrated.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
        ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('already migrated');
    });

    it('fails migration when vhost is native', function () {
        $vhost = FleetVhost::create([
            'domain' => 'native.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'native',
        ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('native NS 3.0');
    });

    it('fails migration when vhost has no configuration', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-config.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('No vhost configuration');
    });

    it('successfully migrates validated vhost with backup', function () {
        $vhost = FleetVhost::create([
            'domain' => 'migrate-me.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        // Set required configuration
        $vhost->setEnvVars([
            'VHOST' => 'migrate-me.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/migrate-me.test',
            'WPATH' => '/srv/migrate-me.test/web',
            'MPATH' => '/srv/migrate-me.test/msg',
            'WUGID' => '33',
        ]);

        // Mock backup creation
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'tar czf')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => 'ARCHIVE_PATH=/srv/migrate-me.test/.archive/pre-migration-20251009-120000.tar.gz',
                'exit_code' => 0,
            ]);

        // Mock structural migration
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'mkdir -p') && str_contains($script, 'web/app')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => 'CHANGES=created_web_app_dir,moved_var_log,moved_var_run,restructured_web_content,',
                'exit_code' => 0,
            ]);

        // Mock permissions update
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'chown') && str_contains($script, 'chmod')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => 'PERMISSIONS_UPDATED=true',
                'exit_code' => 0,
            ]);

        // Mock service reload
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'systemctl reload')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => "nginx_reloaded\nphp_fpm_reloaded",
                'exit_code' => 0,
            ]);

        // Mock verification
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'CHECKS=')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => 'CHECKS=app_public_exists,log_dir_exists,run_dir_exists,index_file_exists,',
                'exit_code' => 0,
            ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('migration_log')
            ->and($result['migration_log']['status'])->toBe('completed')
            ->and($result['migration_log']['steps_completed'])->toContain('backup_creation')
            ->and($result['migration_log']['steps_completed'])->toContain('structural_migration')
            ->and($result['migration_log']['steps_completed'])->toContain('verification');

        // Check database was updated
        $vhost->refresh();
        expect($vhost->migration_status)->toBe('migrated')
            ->and($vhost->migrated_at)->not->toBeNull()
            ->and($vhost->rollback_available)->toBeTrue()
            ->and($vhost->migration_backup_path)->toContain('.archive/pre-migration');
    });

    it('successfully migrates without backup when skipBackup is true', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-backup.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'no-backup.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/no-backup.test',
            'WPATH' => '/srv/no-backup.test/web',
            'MPATH' => '/srv/no-backup.test/msg',
            'WUGID' => '33',
        ]);

        // Mock all steps except backup (should not be called)
        $this->mockRemoteExecution->shouldReceive('executeScript')->times(4)->andReturn([
            'success' => true,
            'output' => 'CHANGES=created_web_app_dir,',
            'exit_code' => 0,
        ], [
            'success' => true,
            'output' => 'PERMISSIONS_UPDATED=true',
            'exit_code' => 0,
        ], [
            'success' => true,
            'output' => 'nginx_reloaded',
            'exit_code' => 0,
        ], [
            'success' => true,
            'output' => 'CHECKS=app_public_exists,log_dir_exists,run_dir_exists,',
            'exit_code' => 0,
        ]);

        $result = $this->migrationService->migrateVhost($vhost, skipBackup: true);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['migration_log']['warnings'])->toContain('Backup skipped (--no-backup flag)');

        // Check rollback_available is false
        $vhost->refresh();
        expect($vhost->rollback_available)->toBeFalse();
    });

    it('fails migration and updates status to failed on error', function () {
        $vhost = FleetVhost::create([
            'domain' => 'fail-me.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'validated',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'fail-me.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/fail-me.test',
            'WPATH' => '/srv/fail-me.test/web',
            'WUGID' => '33',
        ]);

        // Mock backup creation failure
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Disk full',
                'exit_code' => 1,
            ]);

        $result = $this->migrationService->migrateVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('Backup creation failed');

        // Check status updated to failed
        $vhost->refresh();
        expect($vhost->migration_status)->toBe('failed')
            ->and($vhost->migration_issues)->toHaveKey('migration_execution')
            ->and($vhost->migration_issues['migration_execution']['status'])->toBe('failed');
    });

    it('successfully rolls back a migrated vhost', function () {
        $vhost = FleetVhost::create([
            'domain' => 'rollback-me.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => true,
            'migration_backup_path' => '/srv/rollback-me.test/.archive/pre-migration-20251009.tar.gz',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'rollback-me.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'UPATH' => '/srv/rollback-me.test',
            'WUGID' => '33',
        ]);

        // Mock rollback script execution
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->with(
                Mockery::on(fn ($host) => $host === 'test-vnode'),
                Mockery::on(fn ($script) => str_contains($script, 'tar xzf')),
                [],
                true,
                Mockery::any()
            )
            ->andReturn([
                'success' => true,
                'output' => 'ROLLBACK_SUCCESS=true',
                'exit_code' => 0,
            ]);

        $result = $this->migrationService->rollbackVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['archive_restored'])->toContain('pre-migration-20251009.tar.gz');

        // Check status reset to validated
        $vhost->refresh();
        expect($vhost->migration_status)->toBe('validated')
            ->and($vhost->migration_issues)->toHaveKey('rollback_execution');
    });

    it('fails rollback when rollback is not available', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-rollback.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
            'rollback_available' => false, // No rollback available
        ]);

        $result = $this->migrationService->rollbackVhost($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('No rollback available');
    });

    it('lists available rollback points', function () {
        $vhost = FleetVhost::create([
            'domain' => 'list-rollbacks.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
            'migration_status' => 'migrated',
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/list-rollbacks.test',
        ]);

        // Mock list command
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->andReturn([
                'success' => true,
                'output' => "1696867200.0 ./pre-migration-20251009-120000.tar.gz\n1696780800.0 ./pre-migration-20251008-100000.tar.gz",
                'exit_code' => 0,
            ]);

        $result = $this->migrationService->listRollbackPoints($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['rollback_points'])->toHaveCount(2)
            ->and($result['rollback_points'][0])->toHaveKeys(['filename', 'path', 'created_at'])
            ->and($result['rollback_points'][0]['filename'])->toBe('pre-migration-20251009-120000.tar.gz');
    });

    it('returns empty array when no rollback points exist', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-archives.test',
            'vnode_id' => $this->vnode->id,
            'provider' => 'local',
            'status' => 'active',
        ]);

        $vhost->setEnvVars([
            'UPATH' => '/srv/no-archives.test',
        ]);

        // Mock no archives found
        $this->mockRemoteExecution
            ->shouldReceive('executeScript')
            ->once()
            ->andReturn([
                'success' => true,
                'output' => 'NO_ARCHIVES',
                'exit_code' => 0,
            ]);

        $result = $this->migrationService->listRollbackPoints($vhost);

        expect($result)
            ->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['rollback_points'])->toBeEmpty();
    });
});
