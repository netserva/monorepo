<?php

use NetServa\Cli\Services\NetServaContext;
use NetServa\Cli\Services\VhostManagementService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'vhost-management', 'crud', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);
    $this->vhostService = $this->mock(VhostManagementService::class);
});

it('displays help information', function () {
    $this->artisan('addvhost --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Add a new virtual host (NetServa CRUD pattern)')
        ->assertExitCode(0);
});

it('adds vhost successfully with explicit vnode', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn(null);

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('example.com', 'test-server')
        ->andReturn(true);

    $this->artisan('addvhost example.com --vnode=test-server')
        ->expectsOutput('🌐 Adding Virtual Host')
        ->expectsOutput('✅ Successfully added vhost: example.com')
        ->expectsOutput('🔧 Server: test-server')
        ->assertExitCode(0);
});

it('adds vhost successfully with context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('motd');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('test.motd.com', 'motd')
        ->andReturn(true);

    $this->artisan('addvhost test.motd.com')
        ->expectsOutput('🌐 Adding Virtual Host')
        ->expectsOutput('✅ Successfully added vhost: test.motd.com')
        ->expectsOutput('🔧 Server: motd (from context)')
        ->assertExitCode(0);
});

it('prompts for vnode when not provided and no context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn(null);

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('example.org', 'production')
        ->andReturn(true);

    $this->artisan('addvhost example.org')
        ->expectsQuestion('Enter virtual node (server) identifier', 'production')
        ->expectsOutput('✅ Successfully added vhost: example.org')
        ->assertExitCode(0);
});

it('validates domain format', function () {
    $this->artisan('addvhost invalid-domain-format')
        ->expectsOutput('❌ Invalid domain format: invalid-domain-format')
        ->assertExitCode(1);
});

it('handles vhost creation failure', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('test');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('existing.com', 'test')
        ->andReturn(false);

    $this->artisan('addvhost existing.com')
        ->expectsOutput('❌ Failed to add vhost: existing.com')
        ->assertExitCode(1);
});

it('supports dry-run mode', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('staging');

    $this->vhostService
        ->shouldNotReceive('addVhost');

    $this->artisan('addvhost test.staging.com --dry-run')
        ->expectsOutput('🔍 DRY RUN: Add Virtual Host')
        ->expectsOutput('Would add vhost: test.staging.com')
        ->expectsOutput('Target server: staging')
        ->expectsOutput('Operations that would be performed:')
        ->assertExitCode(0);
});

it('shows detailed dry-run information', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn(null);

    $this->artisan('addvhost new.example.com --vnode=prod --dry-run')
        ->expectsOutput('🔍 DRY RUN: Add Virtual Host')
        ->expectsOutput('Would add vhost: new.example.com')
        ->expectsOutput('Target server: prod')
        ->expectsOutput('Operations that would be performed:')
        ->expectsOutput('1. Create virtual host configuration')
        ->expectsOutput('2. Setup directory structure')
        ->expectsOutput('3. Configure web server')
        ->expectsOutput('4. Generate SSL certificates (if applicable)')
        ->assertExitCode(0);
});

it('handles legacy shost parameter', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn(null);

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('legacy.com', 'legacy-server')
        ->andReturn(true);

    $this->artisan('addvhost legacy.com --shost=legacy-server')
        ->expectsOutput('⚠️  Using legacy --shost parameter. Use --vnode in future.')
        ->expectsOutput('✅ Successfully added vhost: legacy.com')
        ->assertExitCode(0);
});

it('validates vnode parameter when provided', function () {
    $this->artisan('addvhost example.com --vnode=""')
        ->expectsOutput('❌ Invalid vnode parameter provided')
        ->assertExitCode(1);
});

it('shows vhost creation steps', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('dev');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('step-test.com', 'dev')
        ->andReturn(true);

    $this->artisan('addvhost step-test.com')
        ->expectsOutput('🌐 Adding Virtual Host')
        ->expectsOutput('📋 Configuration:')
        ->expectsOutput('   Domain: step-test.com')
        ->expectsOutput('   Server: dev (from context)')
        ->expectsOutput('✅ Successfully added vhost: step-test.com')
        ->assertExitCode(0);
});

it('provides post-creation guidance', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('guide');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('guide.example.com', 'guide')
        ->andReturn(true);

    $this->artisan('addvhost guide.example.com')
        ->expectsOutput('✅ Successfully added vhost: guide.example.com')
        ->expectsOutput('📖 Next steps:')
        ->expectsOutput('• Upload your website files')
        ->expectsOutput('• Configure DNS records')
        ->expectsOutput('• Test SSL certificate')
        ->expectsOutput('• php artisan shvhost guide.example.com (view details)')
        ->assertExitCode(0);
});

it('handles special domain formats correctly', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('special');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('sub.domain.example.co.uk', 'special')
        ->andReturn(true);

    $this->artisan('addvhost sub.domain.example.co.uk')
        ->expectsOutput('✅ Successfully added vhost: sub.domain.example.co.uk')
        ->assertExitCode(0);
});

it('shows configuration summary before creation', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('summary');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('summary.test.com', 'summary')
        ->andReturn(true);

    $this->artisan('addvhost summary.test.com')
        ->expectsOutput('📋 Configuration:')
        ->expectsOutput('   Domain: summary.test.com')
        ->expectsOutput('   Server: summary (from context)')
        ->expectsOutput('   Document Root: /srv/summary.test.com/web/app/public')
        ->assertExitCode(0);
});

it('handles very long domain names', function () {
    $longDomain = 'very-long-subdomain.with-multiple-parts.example-domain.co.uk';

    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('long');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with($longDomain, 'long')
        ->andReturn(true);

    $this->artisan("addvhost {$longDomain}")
        ->expectsOutput("✅ Successfully added vhost: {$longDomain}")
        ->assertExitCode(0);
});

it('provides correct context usage info', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->andReturn('context');

    $this->vhostService
        ->shouldReceive('addVhost')
        ->once()
        ->with('context.example.com', 'context')
        ->andReturn(true);

    $this->artisan('addvhost context.example.com')
        ->expectsOutput('🔧 Server: context (from context)')
        ->expectsOutput('💡 Clear context with: php artisan clear-context')
        ->assertExitCode(0);
});
