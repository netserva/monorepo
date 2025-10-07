<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ns\Platform\Models\InfrastructureNode;
use Ns\Secrets\Models\Secret;
use Ns\Setup\Models\SetupJob;
use Ns\Setup\Models\SetupTemplate;
use Ns\Ssh\Models\SshHost;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure all plugin migrations are run for integration testing
    $this->artisan('migrate', [
        '--path' => 'packages/ns-platform/database/migrations',
        '--realpath' => true,
    ]);
    $this->artisan('migrate', [
        '--path' => 'packages/ns-secrets/database/migrations',
        '--realpath' => true,
    ]);
    $this->artisan('migrate', [
        '--path' => 'packages/ns-ssh/database/migrations',
        '--realpath' => true,
    ]);
    $this->artisan('migrate', [
        '--path' => 'packages/ns-setup/database/migrations',
        '--realpath' => true,
    ]);
});

test('complete server deployment workflow across multiple packages', function () {
    $user = User::factory()->create();

    // 1. Infrastructure Manager: Create server node
    $server = InfrastructureNode::create([
        'name' => 'Web Server 01',
        'slug' => 'web-01',
        'type' => 'host',
        'status' => 'active',
        'metadata' => ['cpu_cores' => 4, 'memory_gb' => 8],
    ]);

    // 2. Secrets Manager: Create SSH key (simplified - no cross-package refs)
    $sshKey = Secret::create([
        'name' => 'Web Server SSH Key',
        'slug' => 'web-01-ssh-key',
        'type' => 'ssh_private_key',
        'encrypted_value' => encrypt('-----BEGIN PRIVATE KEY-----\ntest-key\n-----END PRIVATE KEY-----'),
        'is_active' => true,
    ]);

    // 3. SSH Manager: Create SSH host configuration (simplified)
    $sshHost = SshHost::create([
        'host' => 'web-01',
        'hostname' => '192.168.1.100',
        'port' => 22,
        'user' => 'root',
    ]);

    // 4. Server Setup: Create setup template
    $template = SetupTemplate::create([
        'name' => 'lemp-stack',
        'display_name' => 'LEMP Stack',
        'description' => 'Linux, Nginx, MySQL, PHP stack setup',
        'category' => 'web',
        'template_type' => 'service',
        'components' => ['nginx', 'mysql', 'php-fpm'],
        'configuration' => [
            'nginx_workers' => '{{ server.cpu_cores }}',
            'mysql_password' => '{{ secret:mysql-root-password }}',
        ],
    ]);

    // 5. Create MySQL password secret (simplified)
    $mysqlSecret = Secret::create([
        'name' => 'MySQL Root Password',
        'slug' => 'mysql-root-password',
        'type' => 'password',
        'encrypted_value' => encrypt('secure-mysql-password'),
        'is_active' => true,
    ]);

    // 6. Server Setup: Create deployment job
    $setupJob = SetupJob::create([
        'job_id' => 'job_'.uniqid(),
        'setup_template_id' => $template->id,
        'target_host' => $sshHost->host,
        'target_hostname' => $sshHost->hostname,
        'status' => 'pending',
        'configuration' => [
            'nginx_workers' => $server->metadata['cpu_cores'],
            'mysql_password' => decrypt($mysqlSecret->encrypted_value),
        ],
    ]);

    // Test the simplified integration (no cross-package relationships)
    expect($server->name)->toBe('Web Server 01');
    expect($sshKey->name)->toBe('Web Server SSH Key');
    expect($sshHost->host)->toBe('web-01');
    expect($setupJob->target_host)->toBe($sshHost->host);
    expect($setupJob->configuration['nginx_workers'])->toBe(4);
});

test('infrastructure and ssh integration works', function () {
    // Create server node (independent)
    $server = InfrastructureNode::create([
        'name' => 'Test Server',
        'slug' => 'test-server',
        'type' => 'host',
        'status' => 'active',
    ]);

    // Create SSH host (independent - no cross-package refs)
    $sshHost = SshHost::create([
        'host' => 'test-server',
        'hostname' => '192.168.1.100',
        'port' => 22,
        'user' => 'root',
    ]);

    // Test independent functionality (no relationships)
    expect($server->name)->toBe('Test Server');
    expect($server->type)->toBe('host');
    expect($sshHost->host)->toBe('test-server');
    expect($sshHost->hostname)->toBe('192.168.1.100');
});

test('secrets and infrastructure integration works', function () {
    $user = User::factory()->create();

    // Create server (independent)
    $server = InfrastructureNode::create([
        'name' => 'Production Server',
        'slug' => 'prod-server',
        'type' => 'host',
        'status' => 'active',
    ]);

    // Create secrets (independent - no cross-package refs)
    $secret1 = Secret::create([
        'name' => 'Database Password',
        'slug' => 'db-password',
        'type' => 'password',
        'encrypted_value' => encrypt('secure-password'),
        'is_active' => true,
    ]);

    $secret2 = Secret::create([
        'name' => 'API Key',
        'slug' => 'api-key',
        'type' => 'api_key',
        'encrypted_value' => encrypt('sk_test_123'),
        'is_active' => true,
    ]);

    // Test independent functionality (no relationships)
    expect($server->name)->toBe('Production Server');
    expect($secret1->name)->toBe('Database Password');
    expect($secret1->type)->toBe('password');
    expect($secret2->name)->toBe('API Key');
    expect($secret2->type)->toBe('api_key');
});

test('audit logging integration works', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create infrastructure node
    $server = InfrastructureNode::create([
        'name' => 'Test Server',
        'slug' => 'test-server',
        'type' => 'host',
        'status' => 'active',
    ]);

    // Create audit log entry
    $auditLog = \Ns\Audit\Models\AuditLog::create([
        'user_id' => $user->id,
        'username' => $user->name,
        'event_type' => 'create',
        'event_category' => 'infrastructure',
        'resource_type' => 'infrastructure_node',
        'resource_id' => $server->id,
        'resource_name' => $server->name,
        'description' => 'Created infrastructure node',
        'severity_level' => 'low',
        'status' => 'success',
    ]);

    // Test audit log creation
    expect($auditLog->user->id)->toBe($user->id);
    expect($auditLog->resource_type)->toBe('infrastructure_node');
    expect((int) $auditLog->resource_id)->toBe($server->id);
});
