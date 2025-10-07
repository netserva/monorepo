<?php

/**
 * Shared test helper functions for NS
 */
if (! function_exists('mockSshConnectionService')) {
    function mockSshConnectionService()
    {
        // Mock SSH connection service for global integration tests
        app()->bind(\Ns\Ssh\Services\SshConnectionService::class, function () {
            return new class
            {
                public function testConnection($host)
                {
                    return [
                        'success' => true,
                        'latency' => 25,
                        'connection_time' => 150,
                    ];
                }

                public function testMultipleConnections($hosts)
                {
                    return [
                        'success' => true,
                        'total_tested' => count($hosts),
                        'successful' => count($hosts),
                        'failed' => 0,
                    ];
                }

                public function executeCommand($host, $command)
                {
                    return [
                        'exit_code' => 0,
                        'output' => 'Command executed successfully',
                        'error' => '',
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockSystemServiceManager')) {
    function mockSystemServiceManager()
    {
        // Mock system service manager for core tests
        app()->bind(\Ns\Core\Services\SystemServiceManager::class, function () {
            return new class
            {
                public function getServiceStatus($service)
                {
                    return ['status' => 'active', 'running' => true];
                }

                public function startService($service)
                {
                    return true;
                }

                public function stopService($service)
                {
                    return true;
                }

                public function restartService($service)
                {
                    return true;
                }

                public function enableService($service)
                {
                    return true;
                }

                public function disableService($service)
                {
                    return true;
                }

                public function reloadService($service)
                {
                    return true;
                }

                // Alias methods for Feature tests
                public function start($service)
                {
                    return true;
                }

                public function stop($service)
                {
                    return true;
                }

                public function restart($service)
                {
                    return true;
                }

                public function enable($service)
                {
                    return true;
                }

                public function disable($service)
                {
                    return true;
                }

                public function exists($service)
                {
                    return true;
                }

                public function getStatus($service)
                {
                    return 'active';
                }

                public function listServices()
                {
                    return [
                        ['name' => 'nginx', 'status' => 'running', 'description' => 'Nginx Web Server'],
                        ['name' => 'mysql', 'status' => 'running', 'description' => 'MySQL Server'],
                    ];
                }

                public function getAllServices()
                {
                    return [
                        ['name' => 'nginx', 'status' => 'active'],
                        ['name' => 'mysql', 'status' => 'active'],
                    ];
                }

                public function getServicesByStatus($status)
                {
                    return [
                        ['name' => 'nginx', 'status' => $status],
                    ];
                }

                public function refreshAllStatuses()
                {
                    return true;
                }

                public function getServiceLogs($service, $lines = 50)
                {
                    return "Mock log entries for {$service}";
                }

                public function checkServiceDependencies($service)
                {
                    return ['nginx' => true, 'mysql' => true];
                }

                public function validateServiceConfiguration($service)
                {
                    return ['valid' => true, 'message' => 'Configuration valid'];
                }

                public function getServiceHealth($service)
                {
                    return ['healthy' => true, 'status' => 'active', 'last_checked' => now()];
                }

                public function bulkStartServices($services)
                {
                    $results = [];
                    foreach ($services as $service) {
                        $results[$service] = true;
                    }

                    return $results;
                }

                public function bulkStopServices($services)
                {
                    $results = [];
                    foreach ($services as $service) {
                        $results[$service] = true;
                    }

                    return $results;
                }

                public function controlService($service, $action)
                {
                    return ['success' => true, 'output' => "Service {$service} {$action}ed successfully"];
                }

                public function getSystemStatus()
                {
                    return [
                        'nginx' => 'active',
                        'mysql' => 'active',
                    ];
                }

                public function getAvailableServices()
                {
                    return [
                        'nginx' => 'Nginx Web Server',
                        'mysql' => 'MySQL Server',
                    ];
                }

                public function refreshAllStatus()
                {
                    return [
                        'nginx' => 'active',
                        'mysql' => 'active',
                    ];
                }

                public function startAutoStartServices()
                {
                    return [
                        'nginx' => ['success' => true],
                        'mysql' => ['success' => true],
                    ];
                }

                public function getHealthSummary()
                {
                    return [
                        'total' => 2,
                        'running' => 2,
                        'failed' => 0,
                        'stopped' => 0,
                        'health_percentage' => 100,
                    ];
                }

                public function seedDefaultServices()
                {
                    // Mock seed - do nothing
                }
            };
        });
    }
}

if (! function_exists('mockAllSudoCommands')) {
    function mockAllSudoCommands()
    {
        // Mock all systemctl and sudo commands to prevent actual execution
        \Illuminate\Support\Facades\Process::fake([
            // Systemctl commands
            'systemctl start *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl stop *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl restart *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl enable *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl disable *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl reload *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'systemctl is-active *' => \Illuminate\Support\Facades\Process::result('active', '', 0),
            'systemctl status *' => \Illuminate\Support\Facades\Process::result('active (running)', '', 0),
            'systemctl list-units *' => \Illuminate\Support\Facades\Process::result('nginx.service loaded active running', '', 0),
            'systemctl list-unit-files *' => \Illuminate\Support\Facades\Process::result('nginx.service enabled', '', 0),

            // Sudo systemctl commands
            'sudo systemctl start *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo systemctl stop *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo systemctl restart *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo systemctl enable *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo systemctl disable *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo systemctl reload *' => \Illuminate\Support\Facades\Process::result('', '', 0),

            // Mount commands
            'mount' => \Illuminate\Support\Facades\Process::result('/dev/sda1 on / type ext4', '', 0),
            'mountpoint -q *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sshfs *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'fusermount -u *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo mount *' => \Illuminate\Support\Facades\Process::result('', '', 0),
            'sudo umount *' => \Illuminate\Support\Facades\Process::result('', '', 0),

            // Log commands
            'journalctl *' => \Illuminate\Support\Facades\Process::result('Mock log output', '', 0),
            'tail *' => \Illuminate\Support\Facades\Process::result('Mock tail output', '', 0),

            // Other sudo commands
            'sudo *' => \Illuminate\Support\Facades\Process::result('', '', 0),
        ]);
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsProviderService')) {
    function mockDnsProviderService()
    {
        app()->bind(\Ns\Dns\Services\DnsProviderService::class, function () {
            return new class
            {
                public function testConnection($provider)
                {
                    return ['success' => true, 'message' => 'Connection successful'];
                }

                public function createZone($provider, $data)
                {
                    return ['success' => true, 'zone_id' => 'mock-zone-id'];
                }

                public function createRecord($provider, $zone, $data)
                {
                    return ['success' => true, 'record_id' => 'mock-record-id'];
                }

                public function updateRecord($provider, $record, $data)
                {
                    return ['success' => true, 'message' => 'Record updated'];
                }

                public function deleteRecord($provider, $record)
                {
                    return ['success' => true, 'message' => 'Record deleted'];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockFailedSshConnection')) {
    function mockFailedSshConnection($error = 'Connection failed')
    {
        app()->bind(\Ns\Ssh\Services\SshConnectionService::class, function () use ($error) {
            return new class($error)
            {
                private $error;

                public function __construct($error)
                {
                    $this->error = $error;
                }

                public function testConnection($host)
                {
                    return [
                        'success' => false,
                        'error' => $this->error,
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockFailedDnsApi')) {
    function mockFailedDnsApi($error = 'API connection failed')
    {
        app()->bind(\Ns\Dns\Services\DnsProviderService::class, function () use ($error) {
            return new class($error)
            {
                private $error;

                public function __construct($error)
                {
                    $this->error = $error;
                }

                public function testConnection($provider)
                {
                    return ['success' => false, 'error' => $this->error];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockRemoteCommandService')) {
    function mockRemoteCommandService($commands)
    {
        app()->bind(\Ns\Core\Services\RemoteCommandService::class, function () use ($commands) {
            return new class($commands)
            {
                private $commands;

                public function __construct($commands)
                {
                    $this->commands = $commands;
                }

                public function execute($host, $command)
                {
                    return $this->commands[$command] ?? ['exit_code' => 0, 'output' => ''];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockSystemMetricsService')) {
    function mockSystemMetricsService()
    {
        app()->bind(\Ns\Core\Services\SystemMetricsService::class, function () {
            return new class
            {
                public function getCpuUsage()
                {
                    return ['usage' => 25.5, 'cores' => 4];
                }

                public function getMemoryUsage()
                {
                    return ['used' => 4.2, 'total' => 16.0, 'percentage' => 26.25];
                }

                public function getDiskUsage()
                {
                    return ['used' => 120, 'total' => 500, 'percentage' => 24];
                }

                public function getNetworkTraffic()
                {
                    return ['rx' => 1.5, 'tx' => 0.8]; // MB/s
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockSystemLogService')) {
    function mockSystemLogService()
    {
        app()->bind(\Ns\Core\Services\SystemLogService::class, function () {
            return new class
            {
                public function getRecentLogs($limit = 100)
                {
                    return collect([
                        ['level' => 'INFO', 'service' => 'nginx', 'message' => 'Server started', 'timestamp' => now()],
                        ['level' => 'ERROR', 'service' => 'apache', 'message' => 'Port already in use', 'timestamp' => now()->subMinutes(5)],
                        ['level' => 'WARNING', 'service' => 'mysql', 'message' => 'Slow query detected', 'timestamp' => now()->subMinutes(10)],
                    ]);
                }

                public function getLogsByService($service)
                {
                    return collect([
                        ['level' => 'INFO', 'service' => $service, 'message' => 'Service operation', 'timestamp' => now()],
                    ]);
                }

                public function getLogsByLevel($level)
                {
                    return collect([
                        ['level' => $level, 'service' => 'test', 'message' => 'Test message', 'timestamp' => now()],
                    ]);
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}

if (! function_exists('mockDnsPropagationService')) {
    function mockDnsPropagationService($propagated = true)
    {
        // Mock DNS propagation checking service
        app()->bind(\Ns\Dns\Services\DnsPropagationService::class, function () use ($propagated) {
            return new class($propagated)
            {
                private $propagated;

                public function __construct($propagated)
                {
                    $this->propagated = $propagated;
                }

                public function checkRecord($record)
                {
                    return [
                        'success' => true,
                        'propagated' => $this->propagated,
                        'nameservers' => [
                            '8.8.8.8' => $this->propagated,
                            '1.1.1.1' => $this->propagated,
                            '208.67.222.222' => $this->propagated,
                        ],
                    ];
                }
            };
        });
    }
}

if (! function_exists('mockSecretEncryptionService')) {
    function mockSecretEncryptionService()
    {
        app()->bind(\Ns\Secrets\Services\SecretEncryptionService::class, function () {
            return new class
            {
                public function encrypt($value)
                {
                    return base64_encode('encrypted:'.$value);
                }

                public function decrypt($encryptedValue)
                {
                    return str_replace('encrypted:', '', base64_decode($encryptedValue));
                }

                public function isEncrypted($value)
                {
                    return str_starts_with(base64_decode($value), 'encrypted:');
                }
            };
        });
    }
}
if (! function_exists('mockServiceHealthCheck')) {
    function mockServiceHealthCheck(string $url, bool $healthy): void
    {
        if ($healthy) {
            \Illuminate\Support\Facades\Http::fake([
                $url => \Illuminate\Support\Facades\Http::response(['status' => 'ok'], 200),
            ]);
        } else {
            \Illuminate\Support\Facades\Http::fake([
                $url => \Illuminate\Support\Facades\Http::response('Service Unavailable', 503),
            ]);
        }
    }
}
