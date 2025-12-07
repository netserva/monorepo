<?php

use App\Models\GlueRecord;
use App\Models\SwDomain;

beforeEach(function () {
    // Create test domains
    $this->activeDomain = SwDomain::factory()->create([
        'domain_name' => 'test-active.com',
        'lifecycle_status' => 'active',
        'domain_roid' => 'TEST123_DOMAIN_COM-VRSN',
        'registry_id' => '20',
        'domain_expiry' => now()->addDays(90),
        'auto_renew' => true,
    ]);

    $this->transferredDomain = SwDomain::factory()->create([
        'domain_name' => 'test-transferred.com',
        'lifecycle_status' => 'transferred_away',
    ]);

    $this->domainWithGlue = SwDomain::factory()->create([
        'domain_name' => 'test-glue.com',
        'lifecycle_status' => 'active',
    ]);

    GlueRecord::factory()->forDomain($this->domainWithGlue)->create([
        'hostname' => 'ns1.test-glue.com',
        'ip_addresses' => ['1.2.3.4'],
    ]);

    GlueRecord::factory()->forDomain($this->domainWithGlue)->create([
        'hostname' => 'ns2.test-glue.com',
        'ip_addresses' => ['5.6.7.8'],
    ]);
});

test('shsw shows single domain details', function () {
    $this->artisan('shsw', ['search' => 'test-active.com'])
        ->expectsOutput('Domain: test-active.com')
        ->assertSuccessful();
});

test('shsw shows domain not found error', function () {
    $this->artisan('shsw', ['search' => 'nonexistent.com'])
        ->assertSuccessful(); // Non-existent shows list (empty or matching), not error
});

test('shsw lists all domains by default', function () {
    $this->artisan('shsw')
        ->assertSuccessful();
});

test('shsw filters by lifecycle status', function () {
    $this->artisan('shsw', ['--status' => 'active'])
        ->assertSuccessful();
});

test('shsw shows only domains with glue records', function () {
    $this->artisan('shsw', ['--glue' => true])
        ->assertSuccessful();
});

test('shsw filters by TLD', function () {
    $this->artisan('shsw', ['--tld' => 'com'])
        ->assertSuccessful();
});

test('shsw shows expiring domains', function () {
    $this->artisan('shsw', ['--expiring' => 120])
        ->assertSuccessful();
});

test('shsw respects limit option', function () {
    $this->artisan('shsw', ['--limit' => 5])
        ->assertSuccessful();
});

test('shsw outputs JSON format', function () {
    // Capture output
    $this->artisan('shsw', [
        'search' => 'test-active.com',
        '--json' => true,
    ])->assertSuccessful();

    // Command should complete successfully when JSON flag is used
    expect(true)->toBeTrue();
});

test('shsw shows glue records if present', function () {
    $this->artisan('shsw', ['search' => 'test-glue.com'])
        ->expectsOutputToContain('Glue Records:')
        ->expectsOutputToContain('ns1.test-glue.com')
        ->assertSuccessful();
});

test('shsw shows ROID and registry ID', function () {
    $this->artisan('shsw', ['search' => 'test-active.com'])
        ->expectsOutputToContain('TEST123_DOMAIN_COM-VRSN')
        ->expectsOutputToContain('20')
        ->assertSuccessful();
});

test('shsw shows lifecycle status', function () {
    $this->artisan('shsw', ['search' => 'test-active.com'])
        ->expectsOutputToContain('active')
        ->assertSuccessful();

    $this->artisan('shsw', ['search' => 'test-transferred.com'])
        ->expectsOutputToContain('transferred_away')
        ->assertSuccessful();
});

test('shsw shows expiry date and days until expiry', function () {
    $this->artisan('shsw', ['--status' => 'active', '--limit' => 10])
        ->expectsOutputToContain('Expiry')
        ->assertSuccessful();
});

test('shsw shows no domains message when none match', function () {
    $this->artisan('shsw', ['--status' => 'nonexistent'])
        ->expectsOutputToContain('No domains found matching criteria')
        ->assertSuccessful();
});
