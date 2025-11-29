<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Services\TunnelService;
use NetServa\Fleet\Models\FleetVnode;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'tunnel-command', 'priority-2');

beforeEach(function () {
    // Create test VNode
    $this->vnode = FleetVnode::create([
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

    it('handles pdns alias for powerdns', function () {
        artisan('tunnel create test-server pdns')
            ->assertSuccessful();
    });

    it('handles db alias for mysql', function () {
        artisan('tunnel create test-server db')
            ->assertSuccessful();
    });
});

describe('Tunnel Persistence', function () {
    it('reuses existing tunnel when called twice', function () {
        // Mock active tunnel
        $muxDir = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');
        @mkdir($muxDir, 0700, true);
        @touch("{$muxDir}/test-server_19041");

        Process::fake([
            'ssh -S * -O check *' => Process::result(output: ''), // Active
        ]);

        artisan('tunnel create test-server powerdns')
            ->expectsOutputToContain('already active')
            ->assertSuccessful();

        @unlink("{$muxDir}/test-server_19041");
    });
});

describe('Real World Nameserver Scenarios', function () {
    beforeEach(function () {
        // Create nameserver VNodes
        FleetVnode::create([
            'name' => 'ns1gc',
            'technology' => 'native',
            'ssh_host' => 'ns1gc',
            'status' => 'active',
        ]);

        FleetVnode::create([
            'name' => 'ns2gc',
            'technology' => 'native',
            'ssh_host' => 'ns2gc',
            'status' => 'active',
        ]);
    });

    it('creates tunnel for ns1gc nameserver', function () {
        artisan('tunnel create ns1gc powerdns')
            ->assertSuccessful();
    });

    it('creates tunnel for ns2gc nameserver', function () {
        artisan('tunnel create ns2gc powerdns')
            ->assertSuccessful();
    });

    it('handles multiple simultaneous tunnels', function () {
        $muxDir = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');
        @mkdir($muxDir, 0700, true);

        // Create mock socket files for both nameservers
        @touch("{$muxDir}/ns1gc_14291");
        @touch("{$muxDir}/ns2gc_14131");

        Process::fake([
            "ls {$muxDir}/* 2>/dev/null" => Process::result(
                output: "{$muxDir}/ns1gc_14291\n{$muxDir}/ns2gc_14131"
            ),
            'ssh -S * -O check *' => Process::result(output: ''),
        ]);

        artisan('tunnel list')
            ->expectsOutputToContain('ns1gc')
            ->expectsOutputToContain('ns2gc')
            ->expectsOutputToContain('14291')
            ->expectsOutputToContain('14131')
            ->expectsOutputToContain('Total: 2 tunnel(s)')
            ->assertSuccessful();

        @unlink("{$muxDir}/ns1gc_14291");
        @unlink("{$muxDir}/ns2gc_14131");
    });
});

describe('Remote Host Override', function () {
    it('accepts custom remote host', function () {
        artisan('tunnel create test-server powerdns --remote-host=127.0.0.1')
            ->assertSuccessful();
    });

    it('uses localhost as default remote host', function () {
        artisan('tunnel create test-server powerdns')
            ->assertSuccessful();
    });
});

describe('Error Messages', function () {
    it('provides helpful message when VNode not found', function () {
        artisan('tunnel create nonexistent powerdns')
            ->expectsOutput("❌ VNode 'nonexistent' not found in database")
            ->expectsOutputToContain('💡 Run: php artisan addfleet')
            ->assertFailed();
    });

    it('provides clear error on SSH failure', function () {
        Process::fake([
            'ssh *' => Process::result(
                exitCode: 255,
                errorOutput: 'Connection refused'
            ),
        ]);

        artisan('tunnel create test-server powerdns')
            ->expectsOutput('❌ Failed to create tunnel: Connection refused')
            ->assertFailed();
    });
});

describe('Cleanup Operations', function () {
    it('successfully closes multiple tunnels', function () {
        $muxDir = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');
        @mkdir($muxDir, 0700, true);
        @touch("{$muxDir}/test-server_10001");
        @touch("{$muxDir}/test-server_10002");

        Process::fake([
            'ls *' => Process::result(
                output: "{$muxDir}/test-server_10001\n{$muxDir}/test-server_10002"
            ),
            'ssh *' => Process::result(output: ''),
        ]);

        artisan('tunnel close test-server')
            ->expectsOutputToContain('Closed 2 tunnel')
            ->assertSuccessful();

        @unlink("{$muxDir}/test-server_10001");
        @unlink("{$muxDir}/test-server_10002");
    });
});
