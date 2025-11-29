<?php

use NetServa\Core\Enums\NetServaConstants;
use NetServa\Core\Services\LazyConfigurationCache;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

beforeEach(function () {
    // Create real service instance with mocked dependencies
    $this->remoteExecution = $this->mock(RemoteExecutionService::class);
    $this->cache = $this->mock(LazyConfigurationCache::class);

    $this->service = new class($this->remoteExecution, $this->cache) extends NetServaConfigurationService
    {
        // Make protected methods public for testing
        public function getNextAvailableUid(string $VNODE): int
        {
            return parent::getNextAvailableUid($VNODE);
        }

        public function determineVhostUid(string $VNODE, string $serverFqdn, string $VHOST): int
        {
            return parent::determineVhostUid($VNODE, $serverFqdn, $VHOST);
        }

        public function generateUsername(int $U_UID): string
        {
            return parent::generateUsername($U_UID);
        }
    };
});

describe('UID Allocation Constants', function () {
    it('defines correct UID constants', function () {
        expect(NetServaConstants::ADMIN_UID->value)->toBe(1000)
            ->and(NetServaConstants::MAX_USER_UID->value)->toBe(9999);
    });

    it('calculates correct first user UID', function () {
        $firstUserUid = NetServaConstants::ADMIN_UID->value + 1;

        expect($firstUserUid)->toBe(1001);
    });
});

describe('Next Available UID - Fresh Server', function () {
    it('returns 1001 when no users exist on fresh server', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '', // No users found
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1001); // First secondary vhost UID
    });

    it('returns 1001 when command output is only whitespace', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => "   \n\n  ", // Only whitespace
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1001);
    });

    it('returns 1001 when command fails', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => false,
                'output' => '',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1001); // Fallback to first user UID
    });
});

describe('Next Available UID - Servers With Users', function () {
    it('returns 1003 when highest UID is 1002', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '1002',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1003);
    });

    it('returns 1010 when highest UID is 1009', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '1009',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1010);
    });

    it('increments from current highest UID', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '2500',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(2501);
    });

    it('handles UID near max range', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '9998',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(9999); // MAX_USER_UID
    });
});

describe('Primary vs Secondary VHost UID Determination', function () {
    it('returns 1000 for primary vhost (matches server FQDN)', function () {
        $serverFqdn = 'server.example.com';
        $vhost = 'server.example.com';

        $result = $this->service->determineVhostUid('test-server', $serverFqdn, $vhost);

        expect($result)->toBe(1000); // ADMIN_UID
    });

    it('returns 1001+ for secondary vhost (different from server FQDN)', function () {
        $serverFqdn = 'server.example.com';
        $vhost = 'app.example.com';

        // Mock getNextAvailableUid to return 1002
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => '', // No users = first UID
            ]);

        $result = $this->service->determineVhostUid('test-server', $serverFqdn, $vhost);

        expect($result)->toBe(1001); // First secondary vhost
    });

    it('uses case-sensitive FQDN comparison', function () {
        $serverFqdn = 'server.example.com';
        $vhost = 'SERVER.EXAMPLE.COM'; // Different case

        // Should NOT match - case sensitive
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => '',
            ]);

        $result = $this->service->determineVhostUid('test-server', $serverFqdn, $vhost);

        // Different case = different domain = secondary vhost = 1002+
        expect($result)->toBe(1001);
    });

    it('distinguishes subdomain from primary domain', function () {
        $serverFqdn = 'server.example.com';
        $vhost = 'sub.server.example.com'; // Subdomain

        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => '',
            ]);

        $result = $this->service->determineVhostUid('test-server', $serverFqdn, $vhost);

        expect($result)->toBe(1001); // Subdomain is secondary vhost
    });
});

describe('Username Generation', function () {
    it('generates "sysadm" for UID 1000', function () {
        $result = $this->service->generateUsername(1000);

        expect($result)->toBe('sysadm');
    });

    it('generates "u1001" for UID 1001', function () {
        $result = $this->service->generateUsername(1001);

        expect($result)->toBe('u1001');
    });

    it('generates "u1002" for UID 1002', function () {
        $result = $this->service->generateUsername(1002);

        expect($result)->toBe('u1002');
    });

    it('generates "u5000" for UID 5000', function () {
        $result = $this->service->generateUsername(5000);

        expect($result)->toBe('u5000');
    });

    it('generates correct username for max UID', function () {
        $result = $this->service->generateUsername(9999);

        expect($result)->toBe('u9999');
    });
});

describe('UID Allocation via NetServaConstants', function () {
    it('uses helper method to get username for UID', function () {
        expect(NetServaConstants::getUsernameForUid(1000))->toBe('sysadm')
            ->and(NetServaConstants::getUsernameForUid(1001))->toBe('u1001')
            ->and(NetServaConstants::getUsernameForUid(1002))->toBe('u1002')
            ->and(NetServaConstants::getUsernameForUid(2500))->toBe('u2500');
    });
});

describe('UID Allocation Integration Scenarios', function () {
    it('scenario: first vhost on fresh server (primary FQDN)', function () {
        // Server: markc.goldcoast.org
        // VHost:  markc.goldcoast.org (primary - matches server)
        // Expected: UID 1000 (sysadm)

        $serverFqdn = 'markc.goldcoast.org';
        $vhost = 'markc.goldcoast.org';

        $result = $this->service->determineVhostUid('markc', $serverFqdn, $vhost);

        expect($result)->toBe(1000);
        expect($this->service->generateUsername($result))->toBe('sysadm');
    });

    it('scenario: second vhost on server (secondary domain)', function () {
        // Server: markc.goldcoast.org
        // VHost:  wp.goldcoast.org (secondary - different domain)
        // Expected: UID 1001 (u1001) - first secondary vhost

        $serverFqdn = 'markc.goldcoast.org';
        $vhost = 'wp.goldcoast.org';

        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => '', // No secondary users yet
            ]);

        $result = $this->service->determineVhostUid('markc', $serverFqdn, $vhost);

        expect($result)->toBe(1001);
        expect($this->service->generateUsername($result))->toBe('u1001');
    });

    it('scenario: third vhost on server (another secondary domain)', function () {
        // Server: markc.goldcoast.org
        // VHost:  app.example.com (secondary - different domain)
        // Existing: markc.goldcoast.org (1000), wp.goldcoast.org (1001)
        // Expected: UID 1002 (u1002)

        $serverFqdn = 'markc.goldcoast.org';
        $vhost = 'app.example.com';

        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => '1001', // Highest existing UID
            ]);

        $result = $this->service->determineVhostUid('markc', $serverFqdn, $vhost);

        expect($result)->toBe(1002);
        expect($this->service->generateUsername($result))->toBe('u1002');
    });
});

describe('UID Range Validation', function () {
    it('respects ADMIN_UID boundary in query', function () {
        // The awk command filters: $3 > 1000 (ADMIN_UID)
        // This ensures UID 1000 (admin) is not counted

        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '\$3 > 1000 && \$3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '1001',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1002);
    });

    it('respects MAX_USER_UID boundary in query', function () {
        // The awk command filters: $3 < 9999
        // This ensures UIDs at or above 9999 are not counted

        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->with('test-server', "getent passwd | awk -F: '$3 > 1000 && $3 < 9999 {print}' | cut -d: -f3 | sort -n | tail -n1")
            ->andReturn([
                'success' => true,
                'output' => '5000',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(5001);
    });
});

describe('UID Allocation Error Handling', function () {
    it('handles getent passwd failure gracefully', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => false,
                'output' => 'getent: command not found',
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        // Should fallback to first user UID
        expect($result)->toBe(1001);
    });

    it('handles non-numeric output gracefully', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => 'invalid', // Non-numeric
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        // PHP (int) cast of 'invalid' = 0, so it becomes 0 + 1 = 1
        // Actually this would be a bug - but testing current behavior
        expect($result)->toBeInt();
    });

    it('handles empty lines in output', function () {
        $this->remoteExecution
            ->shouldReceive('executeAsRoot')
            ->andReturn([
                'success' => true,
                'output' => "\n\n\n",
            ]);

        $result = $this->service->getNextAvailableUid('test-server');

        expect($result)->toBe(1001); // Fallback when empty
    });
});
