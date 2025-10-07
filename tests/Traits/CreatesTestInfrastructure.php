<?php

namespace Tests\Traits;

use Ns\Dns\Models\DnsProvider;
use Ns\Dns\Models\DnsZone;
use Ns\Platform\Models\InfrastructureNode;
use Ns\Secrets\Models\Secret;
use Ns\Secrets\Models\SecretCategory;
use Ns\Ssh\Models\SshHost;
use Ns\Ssh\Models\SshKey;

trait CreatesTestInfrastructure
{
    /**
     * Create a complete test infrastructure setup
     */
    protected function createTestInfrastructure(): array
    {
        // Create secret category
        $secretCategory = $this->createSecretCategory();

        // Create SSH key and host
        $sshKey = $this->createSshKey();
        $sshHost = $this->createSshHost($sshKey);

        // Create infrastructure node
        $infrastructureNode = $this->createInfrastructureNode($sshHost);

        // Create DNS provider and zone
        $dnsProvider = $this->createDnsProvider();
        $dnsZone = $this->createDnsZone($dnsProvider);

        // Create secrets
        $secrets = $this->createInfrastructureSecrets($secretCategory, $infrastructureNode);

        return [
            'secret_category' => $secretCategory,
            'ssh_key' => $sshKey,
            'ssh_host' => $sshHost,
            'infrastructure_node' => $infrastructureNode,
            'dns_provider' => $dnsProvider,
            'dns_zone' => $dnsZone,
            'secrets' => $secrets,
        ];
    }

    /**
     * Create a secret category for testing
     */
    protected function createSecretCategory(array $attributes = []): SecretCategory
    {
        return SecretCategory::factory()->create(array_merge([
            'name' => 'Infrastructure Secrets',
            'description' => 'Secrets for test infrastructure',
            'status' => 'active',
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * Create an SSH key for testing
     */
    protected function createSshKey(array $attributes = []): SshKey
    {
        return SshKey::factory()->create(array_merge([
            'name' => 'test-infrastructure-key',
            'key_type' => 'ed25519',
            'fingerprint' => 'SHA256:test-infra-'.uniqid(),
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestInfra test-infra@example.com',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create an SSH host for testing
     */
    protected function createSshHost(SshKey $sshKey, array $attributes = []): SshHost
    {
        return SshHost::factory()->create(array_merge([
            'name' => 'test-server',
            'hostname' => 'test-server.example.com',
            'port' => 22,
            'username' => 'root',
            'ssh_key_id' => $sshKey->id,
            'connection_type' => 'key',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create an infrastructure node for testing
     */
    protected function createInfrastructureNode(SshHost $sshHost, array $attributes = []): InfrastructureNode
    {
        return InfrastructureNode::factory()->create(array_merge([
            'name' => 'Test Server Node',
            'hostname' => $sshHost->hostname,
            'ip_address' => '192.168.100.10',
            'node_type' => 'vm',
            'status' => 'active',
            'ssh_host_id' => $sshHost->id,
            'specifications' => [
                'cpu_cores' => 4,
                'memory_gb' => 8,
                'storage_gb' => 100,
                'os' => 'Ubuntu 24.04 LTS',
            ],
            'location' => [
                'datacenter' => 'Test DC',
                'region' => 'us-east-1',
                'zone' => 'us-east-1a',
            ],
        ], $attributes));
    }

    /**
     * Create a DNS provider for testing
     */
    protected function createDnsProvider(array $attributes = []): DnsProvider
    {
        return DnsProvider::factory()->create(array_merge([
            'name' => 'Test DNS Provider',
            'provider_type' => 'cloudflare',
            'api_endpoint' => 'https://api.cloudflare.com/client/v4',
            'is_active' => true,
            'credentials' => [
                'api_token' => 'test-dns-token-'.uniqid(),
                'email' => 'dns-test@example.com',
            ],
        ], $attributes));
    }

    /**
     * Create a DNS zone for testing
     */
    protected function createDnsZone(DnsProvider $dnsProvider, array $attributes = []): DnsZone
    {
        return DnsZone::factory()->create(array_merge([
            'dns_provider_id' => $dnsProvider->id,
            'name' => 'test-infrastructure.example.com',
            'status' => 'active',
            'ttl' => 3600,
            'records_count' => 0,
        ], $attributes));
    }

    /**
     * Create infrastructure-related secrets
     */
    protected function createInfrastructureSecrets(SecretCategory $category, InfrastructureNode $node): array
    {
        return [
            'ssh_private_key' => Secret::factory()->create([
                'name' => 'SSH Private Key - '.$node->name,
                'secret_category_id' => $category->id,
                'value' => '-----BEGIN OPENSSH PRIVATE KEY-----\ntest-private-key-content\n-----END OPENSSH PRIVATE KEY-----',
                'type' => 'ssh_private_key',
                'metadata' => [
                    'infrastructure_node_id' => $node->id,
                    'key_type' => 'ed25519',
                ],
                'is_encrypted' => true,
            ]),
            'root_password' => Secret::factory()->create([
                'name' => 'Root Password - '.$node->name,
                'secret_category_id' => $category->id,
                'value' => 'super-secure-password-'.uniqid(),
                'type' => 'password',
                'metadata' => [
                    'infrastructure_node_id' => $node->id,
                    'username' => 'root',
                ],
                'is_encrypted' => true,
            ]),
            'api_key' => Secret::factory()->create([
                'name' => 'API Key - '.$node->name,
                'secret_category_id' => $category->id,
                'value' => 'api-key-'.uniqid(),
                'type' => 'api_key',
                'metadata' => [
                    'infrastructure_node_id' => $node->id,
                    'service' => 'management_api',
                ],
                'is_encrypted' => true,
            ]),
        ];
    }

    /**
     * Create a multi-node test environment
     */
    protected function createMultiNodeEnvironment(int $nodeCount = 3): array
    {
        $environment = [];
        $secretCategory = $this->createSecretCategory(['name' => 'Multi-Node Environment']);
        $dnsProvider = $this->createDnsProvider();

        for ($i = 1; $i <= $nodeCount; $i++) {
            $sshKey = $this->createSshKey([
                'name' => "node-{$i}-key",
                'fingerprint' => "SHA256:node-{$i}-".uniqid(),
            ]);

            $sshHost = $this->createSshHost($sshKey, [
                'name' => "node-{$i}",
                'hostname' => "node-{$i}.example.com",
            ]);

            $node = $this->createInfrastructureNode($sshHost, [
                'name' => "Node {$i}",
                'hostname' => "node-{$i}.example.com",
                'ip_address' => "192.168.100.{$i}",
            ]);

            $dnsZone = $this->createDnsZone($dnsProvider, [
                'name' => "node-{$i}.example.com",
            ]);

            $secrets = $this->createInfrastructureSecrets($secretCategory, $node);

            $environment["node_{$i}"] = [
                'ssh_key' => $sshKey,
                'ssh_host' => $sshHost,
                'infrastructure_node' => $node,
                'dns_zone' => $dnsZone,
                'secrets' => $secrets,
            ];
        }

        $environment['shared'] = [
            'secret_category' => $secretCategory,
            'dns_provider' => $dnsProvider,
        ];

        return $environment;
    }

    /**
     * Create a production-like test environment
     */
    protected function createProductionEnvironment(): array
    {
        $environment = $this->createMultiNodeEnvironment(5);

        // Add load balancer
        $lbKey = $this->createSshKey(['name' => 'loadbalancer-key']);
        $lbHost = $this->createSshHost($lbKey, [
            'name' => 'loadbalancer',
            'hostname' => 'lb.example.com',
        ]);
        $loadBalancer = $this->createInfrastructureNode($lbHost, [
            'name' => 'Load Balancer',
            'hostname' => 'lb.example.com',
            'ip_address' => '192.168.100.100',
            'node_type' => 'loadbalancer',
            'specifications' => [
                'cpu_cores' => 2,
                'memory_gb' => 4,
                'storage_gb' => 50,
                'os' => 'Ubuntu 24.04 LTS',
            ],
        ]);

        // Add database server
        $dbKey = $this->createSshKey(['name' => 'database-key']);
        $dbHost = $this->createSshHost($dbKey, [
            'name' => 'database',
            'hostname' => 'db.example.com',
        ]);
        $database = $this->createInfrastructureNode($dbHost, [
            'name' => 'Database Server',
            'hostname' => 'db.example.com',
            'ip_address' => '192.168.100.200',
            'node_type' => 'database',
            'specifications' => [
                'cpu_cores' => 8,
                'memory_gb' => 32,
                'storage_gb' => 500,
                'os' => 'Ubuntu 24.04 LTS',
            ],
        ]);

        $environment['loadbalancer'] = [
            'ssh_key' => $lbKey,
            'ssh_host' => $lbHost,
            'infrastructure_node' => $loadBalancer,
        ];

        $environment['database'] = [
            'ssh_key' => $dbKey,
            'ssh_host' => $dbHost,
            'infrastructure_node' => $database,
        ];

        return $environment;
    }

    /**
     * Cleanup test infrastructure
     */
    protected function cleanupTestInfrastructure(array $infrastructure): void
    {
        // Clean up in reverse dependency order
        if (isset($infrastructure['secrets'])) {
            foreach ($infrastructure['secrets'] as $secret) {
                $secret->forceDelete();
            }
        }

        if (isset($infrastructure['dns_zone'])) {
            $infrastructure['dns_zone']->forceDelete();
        }

        if (isset($infrastructure['dns_provider'])) {
            $infrastructure['dns_provider']->forceDelete();
        }

        if (isset($infrastructure['infrastructure_node'])) {
            $infrastructure['infrastructure_node']->forceDelete();
        }

        if (isset($infrastructure['ssh_host'])) {
            $infrastructure['ssh_host']->forceDelete();
        }

        if (isset($infrastructure['ssh_key'])) {
            $infrastructure['ssh_key']->forceDelete();
        }

        if (isset($infrastructure['secret_category'])) {
            $infrastructure['secret_category']->forceDelete();
        }
    }
}
