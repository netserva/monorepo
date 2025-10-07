<?php

use NetServa\Web\Models\VirtualHost;
use NetServa\Web\Models\WebApplication;
use NetServa\Web\Models\WebServer;

uses()
    ->group('unit', 'models', 'virtual-host', 'priority-1');

it('can create a virtual host', function () {
    $webServer = createTestWebServer();

    $vhost = VirtualHost::factory()->create([
        'name' => 'example-vhost',
        'primary_domain' => 'example.com',
        'server_names' => ['example.com', 'www.example.com'],
        'document_root' => '/srv/example.com/web/app/public',
        'web_server_id' => $webServer->id,
        'is_active' => true,
        'ssl_enabled' => true,
    ]);

    expect($vhost)->toBeInstanceOf(VirtualHost::class)
        ->and($vhost->name)->toBe('example-vhost')
        ->and($vhost->primary_domain)->toBe('example.com')
        ->and($vhost->server_names)->toBeArray()
        ->and($vhost->server_names)->toContain('www.example.com')
        ->and($vhost->is_active)->toBeTrue()
        ->and($vhost->ssl_enabled)->toBeTrue()
        ->and($vhost->exists)->toBeTrue();
});

it('belongs to web server', function () {
    $webServer = createTestWebServer();
    $vhost = VirtualHost::factory()->create(['web_server_id' => $webServer->id]);

    expect($vhost->webServer)->toBeInstanceOf(WebServer::class)
        ->and($vhost->webServer->id)->toBe($webServer->id);
});

it('has many web applications', function () {
    $vhost = createTestVirtualHost();

    WebApplication::factory()->count(2)->create([
        'virtual_host_id' => $vhost->id,
    ]);

    expect($vhost->webApplications)->toHaveCount(2)
        ->and($vhost->webApplications->first())->toBeInstanceOf(WebApplication::class);
});

it('can check if SSL certificate is expiring soon', function () {
    $expiringSoonVhost = VirtualHost::factory()->create([
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(15),
    ]);

    $notExpiringVhost = VirtualHost::factory()->create([
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(60),
    ]);

    $noSslVhost = VirtualHost::factory()->create([
        'ssl_enabled' => false,
        'ssl_expires_at' => null,
    ]);

    expect($expiringSoonVhost->isSslExpiringSoon())->toBeTrue()
        ->and($notExpiringVhost->isSslExpiringSoon())->toBeFalse()
        ->and($noSslVhost->isSslExpiringSoon())->toBeFalse();
});

it('can check if SSL certificate is expiring with custom days', function () {
    $vhost = VirtualHost::factory()->create([
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(45),
    ]);

    expect($vhost->isSslExpiringSoon(30))->toBeFalse()
        ->and($vhost->isSslExpiringSoon(60))->toBeTrue();
});

it('can generate nginx configuration', function () {
    $webServer = createTestWebServer(['server_type' => 'nginx']);

    $vhost = VirtualHost::factory()->create([
        'primary_domain' => 'test.example.com',
        'server_names' => ['test.example.com', 'www.test.example.com'],
        'document_root' => '/srv/test.example.com/web/app/public',
        'index_files' => 'index.php index.html',
        'web_server_id' => $webServer->id,
        'ssl_enabled' => true,
        'https_port' => 443,
        'http_port' => 80,
        'force_https' => true,
        'ssl_certificate_path' => '/etc/ssl/certs/test.example.com.crt',
        'ssl_private_key_path' => '/etc/ssl/private/test.example.com.key',
        'php_enabled' => true,
        'php_version' => '8.4',
    ]);

    $config = $vhost->generateConfig();

    expect($config)->toContain('server_name test.example.com www.test.example.com')
        ->and($config)->toContain('root /srv/test.example.com/web/app/public')
        ->and($config)->toContain('ssl_certificate /etc/ssl/certs/test.example.com.crt')
        ->and($config)->toContain('ssl_certificate_key /etc/ssl/private/test.example.com.key')
        ->and($config)->toContain('return 301 https://') // HTTPS redirect
        ->and($config)->toContain('fastcgi_pass unix:'); // PHP configuration
});

it('can generate apache configuration', function () {
    $webServer = createTestWebServer(['server_type' => 'apache']);

    $vhost = VirtualHost::factory()->create([
        'primary_domain' => 'test.example.com',
        'server_names' => ['test.example.com', 'www.test.example.com'],
        'document_root' => '/srv/test.example.com/web/app/public',
        'index_files' => 'index.php index.html',
        'web_server_id' => $webServer->id,
        'ssl_enabled' => true,
        'https_port' => 443,
        'http_port' => 80,
        'ssl_certificate_path' => '/etc/ssl/certs/test.example.com.crt',
        'ssl_private_key_path' => '/etc/ssl/private/test.example.com.key',
    ]);

    $config = $vhost->generateConfig();

    expect($config)->toContain('ServerName test.example.com')
        ->and($config)->toContain('ServerAlias www.test.example.com')
        ->and($config)->toContain('DocumentRoot /srv/test.example.com/web/app/public')
        ->and($config)->toContain('SSLEngine on')
        ->and($config)->toContain('SSLCertificateFile /etc/ssl/certs/test.example.com.crt')
        ->and($config)->toContain('<VirtualHost *:80>')
        ->and($config)->toContain('<VirtualHost *:443>');
});

it('can find active virtual hosts', function () {
    VirtualHost::factory()->create(['is_active' => true]);
    VirtualHost::factory()->create(['is_active' => false]);
    VirtualHost::factory()->create(['is_active' => true]);

    $activeVhosts = VirtualHost::active()->get();

    expect($activeVhosts)->toHaveCount(2)
        ->and($activeVhosts->first()->is_active)->toBeTrue();
});

it('can find default virtual hosts', function () {
    VirtualHost::factory()->create(['is_default' => true]);
    VirtualHost::factory()->create(['is_default' => false]);

    $defaultVhosts = VirtualHost::default()->get();

    expect($defaultVhosts)->toHaveCount(1)
        ->and($defaultVhosts->first()->is_default)->toBeTrue();
});

it('can find virtual hosts by domain', function () {
    VirtualHost::factory()->create([
        'primary_domain' => 'example.com',
        'server_names' => ['example.com', 'www.example.com'],
    ]);

    VirtualHost::factory()->create([
        'primary_domain' => 'test.org',
        'server_names' => ['test.org'],
    ]);

    $exampleVhosts = VirtualHost::byDomain('example.com')->get();
    $testVhosts = VirtualHost::byDomain('test.org')->get();

    expect($exampleVhosts)->toHaveCount(1)
        ->and($exampleVhosts->first()->primary_domain)->toBe('example.com')
        ->and($testVhosts)->toHaveCount(1);
});

it('can find SSL expiring virtual hosts', function () {
    VirtualHost::factory()->create([
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(15),
    ]);

    VirtualHost::factory()->create([
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(60),
    ]);

    VirtualHost::factory()->create([
        'ssl_enabled' => false,
        'ssl_expires_at' => null,
    ]);

    $expiringVhosts = VirtualHost::sslExpiring()->get();

    expect($expiringVhosts)->toHaveCount(1)
        ->and($expiringVhosts->first()->ssl_enabled)->toBeTrue();
});

it('can calculate error rate', function () {
    $vhost = VirtualHost::factory()->create([
        'http_2xx_count' => 800,
        'http_3xx_count' => 100,
        'http_4xx_count' => 80,
        'http_5xx_count' => 20,
    ]);

    $errorRate = $vhost->calculateErrorRate();

    expect($errorRate)->toBe(10.0); // (80 + 20) / 1000 * 100
});

it('returns zero error rate when no requests', function () {
    $vhost = VirtualHost::factory()->create([
        'http_2xx_count' => 0,
        'http_3xx_count' => 0,
        'http_4xx_count' => 0,
        'http_5xx_count' => 0,
    ]);

    $errorRate = $vhost->calculateErrorRate();

    expect($errorRate)->toBe(0.0);
});

it('can deploy application', function () {
    $vhost = createTestVirtualHost();

    $result = $vhost->deploy(['environment' => 'production']);

    expect($result)->toBeTrue()
        ->and($vhost->fresh()->deployment_status)->toBe('deployed')
        ->and($vhost->fresh()->last_deployment_at)->not->toBeNull();
});

it('can check health status', function () {
    $vhost = VirtualHost::factory()->create([
        'is_active' => true,
        'is_responding' => true,
        'response_time_ms' => 150,
        'error_rate_percent' => 2.5,
        'ssl_enabled' => false,
    ]);

    $health = $vhost->checkHealth();

    expect($health)->toBeArray()
        ->and($health['status'])->toBe('healthy')
        ->and($health['message'])->toBe('Virtual host is operating normally')
        ->and($health['checks'])->toBeArray();
});

it('detects maintenance mode in health check', function () {
    $vhost = VirtualHost::factory()->create([
        'maintenance_mode' => true,
        'maintenance_message' => 'Scheduled maintenance',
    ]);

    $health = $vhost->checkHealth();

    expect($health['status'])->toBe('maintenance')
        ->and($health['message'])->toBe('Scheduled maintenance')
        ->and($health['checks'])->toHaveKey('maintenance');
});

it('detects inactive status in health check', function () {
    $vhost = VirtualHost::factory()->create(['is_active' => false]);

    $health = $vhost->checkHealth();

    expect($health['status'])->toBe('error')
        ->and($health['message'])->toBe('Virtual host is inactive');
});

it('detects high response time in health check', function () {
    $vhost = VirtualHost::factory()->create([
        'is_active' => true,
        'is_responding' => true,
        'response_time_ms' => 8000,
    ]);

    $health = $vhost->checkHealth();

    expect($health['status'])->toBe('warning')
        ->and($health['message'])->toBe('High response time')
        ->and($health['checks'])->toHaveKey('response_time');
});

it('detects high error rate in health check', function () {
    $vhost = VirtualHost::factory()->create([
        'is_active' => true,
        'is_responding' => true,
        'response_time_ms' => 150,
        'error_rate_percent' => 12.5,
    ]);

    $health = $vhost->checkHealth();

    expect($health['status'])->toBe('warning')
        ->and($health['message'])->toBe('High error rate')
        ->and($health['checks'])->toHaveKey('error_rate');
});

it('detects SSL certificate expiry in health check', function () {
    $vhost = VirtualHost::factory()->create([
        'is_active' => true,
        'ssl_enabled' => true,
        'ssl_expires_at' => now()->addDays(15),
    ]);

    $health = $vhost->checkHealth();

    expect($health['status'])->toBe('warning')
        ->and($health['message'])->toBe('SSL certificate expires soon')
        ->and($health['checks'])->toHaveKey('ssl_expiry');
});

it('has domain accessor', function () {
    $vhost = VirtualHost::factory()->create(['primary_domain' => 'test.example.com']);

    expect($vhost->domain)->toBe('test.example.com');
});

it('casts server_names to json', function () {
    $serverNames = ['example.com', 'www.example.com', 'mail.example.com'];

    $vhost = VirtualHost::factory()->create(['server_names' => $serverNames]);

    expect($vhost->server_names)->toBeArray()
        ->and($vhost->server_names)->toEqual($serverNames);
});

it('casts boolean fields correctly', function () {
    $vhost = VirtualHost::factory()->create([
        'is_active' => 1,
        'is_default' => 0,
        'ssl_enabled' => 1,
        'force_https' => 1,
        'php_enabled' => 0,
    ]);

    expect($vhost->is_active)->toBeTrue()
        ->and($vhost->is_default)->toBeFalse()
        ->and($vhost->ssl_enabled)->toBeTrue()
        ->and($vhost->force_https)->toBeTrue()
        ->and($vhost->php_enabled)->toBeFalse();
});
