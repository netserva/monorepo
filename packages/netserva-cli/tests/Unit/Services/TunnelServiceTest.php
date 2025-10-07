<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Cli\Services\TunnelService;

uses()
    ->group('unit', 'services', 'tunnel-service', 'priority-1');

beforeEach(function () {
    $this->service = new TunnelService;
});

it('can instantiate tunnel service', function () {
    expect($this->service)->toBeInstanceOf(TunnelService::class);
});

describe('Port Calculation', function () {
    it('calculates correct local port for powerdns service', function () {
        $port = $this->service->calculateLocalPort('markc', 'powerdns');

        // md5("markc\n") = 8d75de8e3b1be1347ca5861962e77957
        // First 3 chars: 8d7 -> 837 (d->3, a-f converted to 0-5)
        // powerdns suffix: 1
        // Result: 18371
        expect($port)->toBe(18371);
    });

    it('calculates correct local port for mysql service', function () {
        $port = $this->service->calculateLocalPort('markc', 'mysql');

        // Same hash as above: 837
        // mysql suffix: 6
        // Result: 18376
        expect($port)->toBe(18376);
    });

    it('calculates correct local port for redis service', function () {
        $port = $this->service->calculateLocalPort('markc', 'redis');

        // Same hash as above: 837
        // redis suffix: 9
        // Result: 18379
        expect($port)->toBe(18379);
    });

    it('calculates correct local port for default api service', function () {
        $port = $this->service->calculateLocalPort('markc', 'api');

        // Same hash as above: 837
        // api suffix: 0
        // Result: 18370
        expect($port)->toBe(18370);
    });

    it('generates different ports for different hosts', function () {
        $port1 = $this->service->calculateLocalPort('host1', 'api');
        $port2 = $this->service->calculateLocalPort('host2', 'api');

        expect($port1)->not->toBe($port2);
    });

    it('generates consistent ports for same host', function () {
        $port1 = $this->service->calculateLocalPort('markc', 'powerdns');
        $port2 = $this->service->calculateLocalPort('markc', 'powerdns');

        expect($port1)->toBe($port2);
    });
});

describe('Service Port Mapping', function () {
    it('returns correct remote port for powerdns', function () {
        $port = $this->service->getRemotePort('powerdns');

        expect($port)->toBe(8081);
    });

    it('returns correct remote port for pdns alias', function () {
        $port = $this->service->getRemotePort('pdns');

        expect($port)->toBe(8081);
    });

    it('returns correct remote port for mysql', function () {
        $port = $this->service->getRemotePort('mysql');

        expect($port)->toBe(3306);
    });

    it('returns correct remote port for db alias', function () {
        $port = $this->service->getRemotePort('db');

        expect($port)->toBe(3306);
    });

    it('returns correct remote port for redis', function () {
        $port = $this->service->getRemotePort('redis');

        expect($port)->toBe(6379);
    });

    it('returns default port for unknown service', function () {
        $port = $this->service->getRemotePort('unknown');

        expect($port)->toBe(8080);
    });
});

describe('Tunnel Creation', function () {
    it('creates tunnel successfully', function () {
        Process::fake([
            'ssh *' => Process::result(output: ''),
        ]);

        $result = $this->service->create('markc', 'powerdns');

        expect($result)->toHaveKeys(['success', 'local_port', 'endpoint'])
            ->and($result['success'])->toBeTrue()
            ->and($result['local_port'])->toBe(18371)
            ->and($result['endpoint'])->toBe('http://localhost:18371');
    });

    it('handles tunnel creation failure', function () {
        Log::spy();

        Process::fake(function ($command) {
            // Always fail SSH commands to simulate connection error
            return Process::result(exitCode: 1, errorOutput: 'Connection failed');
        });

        $result = $this->service->create('markc', 'powerdns');

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeFalse()
            ->and($result)->toHaveKey('error')
            ->and($result['error'])->toContain('Connection failed');
    });

    it('uses custom local port when provided', function () {
        Process::fake([
            'ssh *' => Process::result(output: ''),
        ]);

        $result = $this->service->create('markc', 'powerdns', localPort: 9999);

        expect($result['local_port'])->toBe(9999)
            ->and($result['endpoint'])->toBe('http://localhost:9999');
    });

    it('uses custom remote port when provided', function () {
        Process::fake([
            'ssh *' => Process::result(output: ''),
        ]);

        $result = $this->service->create('markc', 'powerdns', remotePort: 8888);

        expect($result['success'])->toBeTrue();
    });
});

describe('Tunnel Status', function () {
    it('detects inactive tunnel', function () {
        Process::fake([
            'ssh *' => Process::result(exitCode: 1),
        ]);

        $isActive = $this->service->isActive('markc', 10621);

        expect($isActive)->toBeFalse();
    });
});

describe('Tunnel Endpoint', function () {
    it('returns endpoint for active tunnel', function () {
        // Mock socket file
        $socketPath = config('netserva-cli.paths.ssh_mux_dir').'/markc_18371';
        @mkdir(dirname($socketPath), 0700, true);
        @touch($socketPath);

        Process::fake([
            'ssh -S * -O check *' => Process::result(output: ''), // Active tunnel
        ]);

        $result = $this->service->getEndpoint('markc', 'powerdns');

        expect($result)->toHaveKeys(['success', 'endpoint', 'local_port'])
            ->and($result['success'])->toBeTrue()
            ->and($result['endpoint'])->toBe('http://localhost:18371')
            ->and($result['local_port'])->toBe(18371);

        @unlink($socketPath);
    });

    it('returns error for inactive tunnel', function () {
        Process::fake([
            'ssh *' => Process::result(exitCode: 1),
        ]);

        $result = $this->service->getEndpoint('markc', 'powerdns');

        expect($result)->toHaveKeys(['success', 'error'])
            ->and($result['success'])->toBeFalse();
    });
});

describe('Tunnel Ensure', function () {
    it('creates tunnel if not active', function () {
        Process::fake([
            'ssh -S * -O check *' => Process::result(exitCode: 1), // Not active
            'ssh -f *' => Process::result(output: ''), // Create succeeds
        ]);

        $result = $this->service->ensure('markc', 'powerdns');

        expect($result)->toHaveKeys(['success', 'endpoint', 'local_port', 'created'])
            ->and($result['success'])->toBeTrue()
            ->and($result['created'])->toBeTrue()
            ->and($result['endpoint'])->toBe('http://localhost:18371');
    });
});

describe('Tunnel Closure', function () {
    it('closes active tunnel successfully', function () {
        Process::fake([
            'ssh *' => Process::result(output: ''),
        ]);

        $result = $this->service->close('markc', 10621);

        expect($result)->toHaveKeys(['success'])
            ->and($result['success'])->toBeTrue();
    });
});
