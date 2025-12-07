<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetServa\Core\Services\NetServaContext;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'netserva-fleet', 'vhost-management', 'crud', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);

    $this->vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $this->vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $this->vsite->id]);
    $this->vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $this->vnode->id,
        'status' => 'active',
        'php_version' => '8.2',
        'ssl_enabled' => false,
        'document_root' => '/srv/example.com/web',
    ]);
});

it('displays help information', function () {
    $this->artisan('chvhost --help')
        ->expectsOutputToContain('Description:')
        ->expectsOutputToContain('Change/update virtual host configuration')
        ->assertExitCode(0);
});

it('updates PHP version successfully', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --php-version=8.4')
        ->expectsOutputToContain('Updating VHost')
        ->expectsOutputToContain('php_version')
        ->expectsOutputToContain('8.4')
        ->expectsOutputToContain('updated successfully')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->php_version)->toBe('8.4');
});

it('validates PHP version options', function () {
    $this->artisan('chvhost markc example.com --php-version=7.4')
        ->expectsOutputToContain('Invalid PHP version')
        ->expectsOutputToContain('8.1, 8.2, 8.3, 8.4')
        ->assertExitCode(1);

    $this->vhost->refresh();
    expect($this->vhost->php_version)->toBe('8.2');
});

it('enables SSL successfully', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --ssl=true')
        ->expectsOutputToContain('Updating VHost')
        ->expectsOutputToContain('ssl_enabled')
        ->expectsOutputToContain('updated successfully')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->ssl_enabled)->toBeTrue();
});

it('disables SSL successfully', function () {
    $this->vhost->update(['ssl_enabled' => true]);

    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --ssl=false')
        ->expectsOutputToContain('ssl_enabled')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->ssl_enabled)->toBeFalse();
});

it('validates SSL value', function () {
    $this->artisan('chvhost markc example.com --ssl=invalid')
        ->expectsOutputToContain('Invalid SSL value')
        ->assertExitCode(1);
});

it('updates webroot successfully', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --webroot=/srv/example.com/public')
        ->expectsOutputToContain('Updating VHost')
        ->expectsOutputToContain('document_root')
        ->expectsOutputToContain('/srv/example.com/public')
        ->expectsOutputToContain('updated successfully')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->document_root)->toBe('/srv/example.com/public');
});

it('validates webroot is absolute path', function () {
    $this->artisan('chvhost markc example.com --webroot=relative/path')
        ->expectsOutputToContain('Webroot must be an absolute path')
        ->assertExitCode(1);

    $this->vhost->refresh();
    expect($this->vhost->document_root)->toBe('/srv/example.com/web');
});

it('updates multiple options at once', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --php-version=8.4 --ssl=true --webroot=/srv/example.com/app')
        ->expectsOutputToContain('php_version')
        ->expectsOutputToContain('ssl_enabled')
        ->expectsOutputToContain('document_root')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->php_version)->toBe('8.4');
    expect($this->vhost->ssl_enabled)->toBeTrue();
    expect($this->vhost->document_root)->toBe('/srv/example.com/app');
});

it('supports dry-run mode', function () {
    $this->artisan('chvhost markc example.com --php-version=8.4 --dry-run')
        ->expectsOutputToContain('Updating VHost')
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->php_version)->toBe('8.2');
});

it('requires at least one option to be specified', function () {
    $this->artisan('chvhost markc example.com')
        ->expectsOutputToContain('No changes specified')
        ->assertExitCode(1);
});

it('handles missing vhost gracefully', function () {
    $this->artisan('chvhost markc nonexistent.com --php-version=8.4')
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('handles missing vnode gracefully', function () {
    $this->artisan('chvhost nonexistent example.com --php-version=8.4')
        ->expectsOutputToContain('VNode nonexistent not found')
        ->assertExitCode(1);
});

it('validates required vnode argument', function () {
    $this->artisan('chvhost')
        ->assertExitCode(1);
});

it('validates required vhost argument', function () {
    $this->artisan('chvhost markc')
        ->assertExitCode(1);
});

it('shows all valid PHP versions in error message', function () {
    $this->artisan('chvhost markc example.com --php-version=9.0')
        ->expectsOutputToContain('8.1, 8.2, 8.3, 8.4')
        ->assertExitCode(1);
});

it('uses correct NetServa 3.0 command signature pattern', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --php-version=8.4')
        ->expectsOutputToContain('updated successfully')
        ->assertExitCode(0);
});

it('updates status successfully', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->once();

    $this->artisan('chvhost markc example.com --status=inactive')
        ->expectsOutputToContain('status')
        ->expectsOutputToContain('inactive')
        ->assertExitCode(0);

    $this->vhost->refresh();
    expect($this->vhost->status)->toBe('inactive');
});

it('validates status value', function () {
    $this->artisan('chvhost markc example.com --status=invalid')
        ->expectsOutputToContain('Invalid status')
        ->assertExitCode(1);
});
