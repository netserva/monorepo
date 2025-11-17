<?php

use NetServa\Fleet\Console\Commands\FleetImportCommand;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses()
    ->group('feature', 'fleet', 'import', 'priority-2');

beforeEach(function () {
    // Mock var directory structure
    $this->varBase = '/tmp/test-ns-var';
    config(['fleet.import.var_base_path' => $this->varBase]);

    // Create test directory structure
    if (! is_dir($this->varBase)) {
        mkdir($this->varBase, 0755, true);
    }

    mkdir("{$this->varBase}/mgo", 0755, true);
    mkdir("{$this->varBase}/nsorg", 0755, true);

    // Create test vhost files
    file_put_contents("{$this->varBase}/mgo/goldcoast.org", "VHOST=\"goldcoast.org\"\nVNODE=\"mgo\"\n");
    file_put_contents("{$this->varBase}/mgo/test.example.com", "VHOST=\"test.example.com\"\nVNODE=\"mgo\"\n");
    file_put_contents("{$this->varBase}/nsorg/netserva.org", "VHOST=\"netserva.org\"\nVNODE=\"nsorg\"\n");
});

afterEach(function () {
    // Clean up test directory
    if (is_dir($this->varBase)) {
        exec("rm -rf {$this->varBase}");
    }
});

it('can run import command in dry-run mode', function () {
    $this->artisan(FleetImportCommand::class, ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('Would create/update VSite')
        ->expectsOutputToContain('Would create/update VNode')
        ->expectsOutputToContain('Would import VHost');

    // No records should be created in dry-run
    expect(FleetVsite::count())->toBe(0)
        ->and(FleetVnode::count())->toBe(0)
        ->and(FleetVhost::count())->toBe(0);
});

it('can import infrastructure from var directory', function () {
    $this->artisan(FleetImportCommand::class)
        ->assertExitCode(0)
        ->expectsOutputToContain('Import completed successfully');

    // Check that VSites were created
    expect(FleetVsite::count())->toBeGreaterThan(0);

    $localIncus = FleetVsite::where('name', 'local-incus')->first();
    expect($localIncus)->not->toBeNull()
        ->provider->toBe('local')
        ->technology->toBe('incus');

    // Check that VNodes were created
    expect(FleetVnode::count())->toBeGreaterThan(0);

    $mgoNode = FleetVnode::where('name', 'mgo')->first();
    expect($mgoNode)->not->toBeNull()
        ->vsite_id->toBe($localIncus->id);

    // Check that VHosts were created
    expect(FleetVhost::count())->toBeGreaterThan(0);

    $goldcoastVHost = FleetVhost::where('domain', 'goldcoast.org')->first();
    expect($goldcoastVHost)->not->toBeNull()
        ->vnode_id->toBe($mgoNode->id);
});

it('can import specific vnode only', function () {
    $this->artisan(FleetImportCommand::class, ['--vnode' => 'mgo'])
        ->assertExitCode(0);

    // Should have created VNode mgo but not nsorg
    expect(FleetVnode::where('name', 'mgo')->exists())->toBeTrue()
        ->and(FleetVnode::where('name', 'nsorg')->exists())->toBeFalse();

    // Should have created VHosts for mgo only
    expect(FleetVhost::where('domain', 'goldcoast.org')->exists())->toBeTrue()
        ->and(FleetVhost::where('domain', 'netserva.org')->exists())->toBeFalse();
});

it('can force overwrite existing data', function () {
    // Create initial import
    $this->artisan(FleetImportCommand::class)->assertExitCode(0);

    $initialCount = FleetVhost::count();

    // Add another vhost file
    file_put_contents("{$this->varBase}/mgo/new.example.com", "VHOST=\"new.example.com\"\nVNODE=\"mgo\"\n");

    // Import with force
    $this->artisan(FleetImportCommand::class, ['--force' => true])
        ->assertExitCode(0);

    // Should have imported the new vhost
    expect(FleetVhost::count())->toBe($initialCount + 1)
        ->and(FleetVhost::where('domain', 'new.example.com')->exists())->toBeTrue();
});

it('correctly maps vnodes to vsites', function () {
    $this->artisan(FleetImportCommand::class)->assertExitCode(0);

    $mgoNode = FleetVnode::where('name', 'mgo')->first();
    $nsorgNode = FleetVnode::where('name', 'nsorg')->first();

    expect($mgoNode->vsite->provider)->toBe('local')
        ->and($mgoNode->vsite->technology)->toBe('incus')
        ->and($nsorgNode->vsite->provider)->toBe('binarylane')
        ->and($nsorgNode->vsite->technology)->toBe('vps');
});

it('loads environment variables from vhost files', function () {
    $this->artisan(FleetImportCommand::class)->assertExitCode(0);

    $vhost = FleetVhost::where('domain', 'goldcoast.org')->first();

    expect($vhost->environment_vars)->toBeArray()
        ->and($vhost->getEnvVar('VHOST'))->toBe('goldcoast.org')
        ->and($vhost->getEnvVar('VNODE'))->toBe('mgo');
});

it('excludes files matching exclude patterns', function () {
    // Create files that should be excluded
    file_put_contents("{$this->varBase}/mgo/.hidden", 'hidden file');
    file_put_contents("{$this->varBase}/mgo/backup.bak", 'backup file');
    file_put_contents("{$this->varBase}/mgo/temp.tmp", 'temp file');

    $this->artisan(FleetImportCommand::class)->assertExitCode(0);

    // Should not have imported excluded files
    expect(FleetVhost::where('domain', '.hidden')->exists())->toBeFalse()
        ->and(FleetVhost::where('domain', 'backup.bak')->exists())->toBeFalse()
        ->and(FleetVhost::where('domain', 'temp.tmp')->exists())->toBeFalse();
});

it('handles missing var directory gracefully', function () {
    config(['fleet.import.var_base_path' => '/nonexistent/path']);

    $this->artisan(FleetImportCommand::class)
        ->assertExitCode(1)
        ->expectsOutputToContain('Var directory not found');
});

it('shows import statistics', function () {
    $this->artisan(FleetImportCommand::class)
        ->assertExitCode(0)
        ->expectsOutputToContain('Import Results:')
        ->expectsOutputToContain('VSites')
        ->expectsOutputToContain('VNodes')
        ->expectsOutputToContain('VHosts');
});

it('can guess vnode roles correctly', function () {
    // Create additional test directories with role-specific names
    mkdir("{$this->varBase}/router", 0755, true);
    mkdir("{$this->varBase}/storage", 0755, true);
    mkdir("{$this->varBase}/haproxy", 0755, true);

    file_put_contents("{$this->varBase}/router/config", 'test');
    file_put_contents("{$this->varBase}/storage/data", 'test');
    file_put_contents("{$this->varBase}/haproxy/config", 'test');

    $this->artisan(FleetImportCommand::class)->assertExitCode(0);

    $routerNode = FleetVnode::where('name', 'router')->first();
    $storageNode = FleetVnode::where('name', 'storage')->first();
    $haproxyNode = FleetVnode::where('name', 'haproxy')->first();

    expect($routerNode->role)->toBe('network')
        ->and($storageNode->role)->toBe('storage')
        ->and($haproxyNode->role)->toBe('mixed');
});
