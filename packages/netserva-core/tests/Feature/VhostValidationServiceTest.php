<?php

use NetServa\Core\Services\VhostValidationService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * VHost Validation Service Tests
 *
 * Tests the validation service for NetServa 3.0 compliance checking
 */
describe('VhostValidationService', function () {
    beforeEach(function () {
        $this->validationService = app(VhostValidationService::class);

        // Use factories to create test data
        $this->vnode = FleetVnode::factory()->create([
            'name' => 'test-vnode',
            'role' => 'compute',
            'environment' => 'testing',
        ]);
    });

    it('fails validation when vhost has no configuration', function () {
        $vhost = FleetVhost::create([
            'domain' => 'no-config.test',
            'vnode_id' => $this->vnode->id,
            'status' => 'active',
            'migration_status' => 'discovered',
        ]);

        $result = $this->validationService->validateVhost($vhost);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result)->toHaveKey('error')
            ->and($result['error'])->toContain('no configuration variables');
    });

    it('validates vhost with complete configuration', function () {
        $vhost = FleetVhost::create([
            'domain' => 'complete.test',
            'vnode_id' => $this->vnode->id,
            'status' => 'active',
            'migration_status' => 'discovered',
        ]);

        // Add basic configuration
        $vhost->setEnvVars([
            'VHOST' => 'complete.test',
            'VNODE' => 'test-vnode',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/complete.test',
            'WPATH' => '/srv/complete.test/web',
            'MPATH' => '/srv/complete.test/msg',
            'C_FPM' => '/etc/php/8.4/fpm',
            'OSTYP' => 'debian',
            'SQCMD' => 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db',
        ]);

        $result = $this->validationService->validateVhost($vhost);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKeys(['status', 'issues', 'warnings', 'passed', 'summary'])
            ->and($result['summary'])->toHaveKeys(['total_checks', 'passed', 'warnings', 'errors']);
    });

    it('stores validation results in database', function () {
        $vhost = FleetVhost::create([
            'domain' => 'storable.test',
            'vnode_id' => $this->vnode->id,
            'status' => 'active',
            'migration_status' => 'discovered',
        ]);

        $vhost->setEnvVars([
            'VHOST' => 'storable.test',
            'UUSER' => 'u1000',
            'U_UID' => '1000',
            'U_GID' => '1000',
            'UPATH' => '/srv/storable.test',
            'WPATH' => '/srv/storable.test/web',
            'MPATH' => '/srv/storable.test/msg',
        ]);

        $validationResult = [
            'success' => true,
            'status' => 'passed',
            'issues' => [],
            'warnings' => [
                [
                    'category' => 'test',
                    'message' => 'Test warning',
                ],
            ],
            'passed' => [],
            'summary' => [
                'total_checks' => 10,
                'passed' => 9,
                'warnings' => 1,
                'errors' => 0,
            ],
        ];

        $this->validationService->updateVhostValidation($vhost, $validationResult);

        $vhost->refresh();

        expect($vhost->migration_status)->toBe('validated')
            ->and($vhost->migration_issues)->toBeArray()
            ->and($vhost->migration_issues)->toHaveKey('validation_status')
            ->and($vhost->migration_issues['validation_status'])->toBe('passed')
            ->and($vhost->migration_issues)->toHaveKey('summary')
            ->and($vhost->migration_issues['summary']['passed'])->toBe(9);
    });

    it('categorizes validation status correctly', function () {
        $vhost = FleetVhost::create([
            'domain' => 'status-test.test',
            'vnode_id' => $this->vnode->id,
            'status' => 'active',
            'migration_status' => 'discovered',
        ]);

        // Test 'needs_fixes' status doesn't change migration_status
        $validationResult = [
            'success' => true,
            'status' => 'needs_fixes',
            'issues' => [['severity' => 'error', 'category' => 'test', 'message' => 'Error']],
            'warnings' => [],
            'passed' => [],
            'summary' => ['total_checks' => 1, 'passed' => 0, 'warnings' => 0, 'errors' => 1],
        ];

        $this->validationService->updateVhostValidation($vhost, $validationResult);
        $vhost->refresh();

        expect($vhost->migration_status)->toBe('discovered'); // Should stay as discovered

        // Test 'passed' status updates migration_status to validated
        $validationResult['status'] = 'passed';
        $validationResult['issues'] = [];
        $validationResult['summary']['errors'] = 0;

        $this->validationService->updateVhostValidation($vhost, $validationResult);
        $vhost->refresh();

        expect($vhost->migration_status)->toBe('validated');
    });
});
