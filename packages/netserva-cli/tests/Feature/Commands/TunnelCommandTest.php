<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use NetServa\Cli\Services\TunnelService;
use NetServa\Fleet\Models\FleetVNode;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'tunnel-command', 'priority-2');

beforeEach(function () {
    // Create test VNode
    $this->vnode = FleetVNode::create([
        'name' => 'test-server',
        'technology' => 'native',
        'ssh_host' => 'test-server',
        'status' => 'active',
    ]);

    // Mock SSH processes
    Process::fake([
        'ssh *' => Process::result(output: ''),
    ]);
});

describe('Tunnel Create Command', function () {
    it('creates tunnel successfully', function () {
        artisan('tunnel create test-server powerdns')
            ->expectsOutput('✅ Tunnel created on port 19041')
            ->assertSuccessful();
    });

    it('fails with invalid host', function () {
        artisan('tunnel create nonexistent-host powerdns')
            ->expectsOutput("❌ VNode 'nonexistent-host' not found in database")
            ->assertFailed();
    });

    it('accepts custom local port', function () {
        artisan('tunnel create test-server powerdns --local-port=9999')
            ->assertSuccessful();
    });

    it('accepts custom remote port', function () {
        artisan('tunnel create test-server powerdns --remote-port=8888')
            ->assertSuccessful();
    });
});

describe('Tunnel Close Command', function () {
    it('closes tunnel successfully', function () {
        artisan('tunnel close test-server --local-port=19041')
            ->assertSuccessful();
    });

    it('closes all tunnels for host', function () {
        artisan('tunnel close test-server')
            ->assertSuccessful();
    });
});

describe('Tunnel Check Command', function () {
    it('checks if tunnel is active', function () {
        Process::fake([
            'ssh -S * -O check *' => Process::result(exitCode: 1), // Not active
        ]);

        artisan('tunnel check test-server powerdns')
            ->expectsOutput('ℹ️  No active tunnel on port 19041')
            ->assertFailed();
    });
});

describe('Tunnel Endpoint Command', function () {
    it('returns endpoint URL when active', function () {
        // Mock active tunnel
        Process::fake([
            'ssh -S * -O check *' => Process::result(output: ''), // Active
        ]);

        // Manually create socket file for test
        $service = app(TunnelService::class);
        $socketPath = config('netserva-cli.paths.ssh_mux_dir').'/test-server_19041';
        @mkdir(dirname($socketPath), 0700, true);
        @touch($socketPath);

        artisan('tunnel endpoint test-server powerdns')
            ->expectsOutput('http://localhost:19041')
            ->assertSuccessful();

        @unlink($socketPath);
    });

    it('fails when tunnel not active', function () {
        Process::fake([
            'ssh -S * -O check *' => Process::result(exitCode: 1),
        ]);

        artisan('tunnel endpoint test-server powerdns')
            ->assertFailed();
    });
});

describe('Tunnel Ensure Command', function () {
    it('creates tunnel if not active', function () {
        Process::fake([
            'ssh -S * -O check *' => Process::result(exitCode: 1), // Not active
            'ssh -f *' => Process::result(output: ''), // Create succeeds
        ]);

        artisan('tunnel ensure test-server powerdns')
            ->expectsOutput('✅ Tunnel created on port 19041')
            ->assertSuccessful();
    });
});

describe('Tunnel List Command', function () {
    it('lists all active tunnels', function () {
        artisan('tunnel list')
            ->expectsOutput('ℹ️  No active SSH tunnels')
            ->assertSuccessful();
    });

    it('displays tunnels in table format', function () {
        // Mock socket files
        $muxDir = config('netserva-cli.paths.ssh_mux_dir');
        @mkdir($muxDir, 0700, true);
        @touch("{$muxDir}/test-server_19041");

        Process::fake([
            "ls {$muxDir}/* 2>/dev/null" => Process::result(output: "{$muxDir}/test-server_19041"),
            'ssh -S * -O check *' => Process::result(output: ''), // Active
        ]);

        artisan('tunnel list')
            ->expectsOutputToContain('test-server')
            ->expectsOutputToContain('19041')
            ->assertSuccessful();

        @unlink("{$muxDir}/test-server_19041");
    });
});

describe('Invalid Action', function () {
    it('handles invalid action gracefully', function () {
        artisan('tunnel invalid-action test-server')
            ->expectsOutput('❌ Invalid action: invalid-action')
            ->assertFailed();
    });

    it('displays help for invalid action', function () {
        artisan('tunnel invalid-action test-server')
            ->expectsOutputToContain('Valid actions:')
            ->expectsOutputToContain('create')
            ->expectsOutputToContain('close')
            ->expectsOutputToContain('check')
            ->assertFailed();
    });
});

describe('Service Types', function () {
    it('handles powerdns service', function () {
        artisan('tunnel create test-server powerdns')
            ->assertSuccessful();
    });

    it('handles mysql service', function () {
        artisan('tunnel create test-server mysql')
            ->assertSuccessful();
    });

    it('handles redis service', function () {
        artisan('tunnel create test-server redis')
            ->assertSuccessful();
    });

    it('handles api service', function () {
        artisan('tunnel create test-server api')
            ->assertSuccessful();
    });
});
