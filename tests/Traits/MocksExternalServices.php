<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Http;

trait MocksExternalServices
{
    /**
     * Mock successful CloudFlare API responses
     */
    protected function mockCloudFlareApi(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'test-zone-id',
                        'name' => 'example.com',
                        'status' => 'active',
                        'name_servers' => ['ns1.cloudflare.com', 'ns2.cloudflare.com'],
                    ],
                ],
                'result_info' => ['count' => 1, 'page' => 1, 'per_page' => 50, 'total_count' => 1],
            ], 200),

            'api.cloudflare.com/client/v4/zones/*/dns_records' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'test-record-id',
                        'type' => 'A',
                        'name' => 'www.example.com',
                        'content' => '192.168.100.10',
                        'ttl' => 3600,
                    ],
                ],
            ], 200),

            'api.cloudflare.com/client/v4/zones/*/dns_records/*' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'test-record-id',
                    'type' => 'A',
                    'name' => 'www.example.com',
                    'content' => '192.168.100.10',
                    'ttl' => 3600,
                ],
            ], 200),
        ]);
    }

    /**
     * Mock failed CloudFlare API responses
     */
    protected function mockCloudFlareApiFailure(string $errorMessage = 'API request failed'): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [
                    ['code' => 9999, 'message' => $errorMessage],
                ],
            ], 400),
        ]);
    }

    /**
     * Mock CloudFlare API rate limiting
     */
    protected function mockCloudFlareRateLimit(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [
                    ['code' => 10013, 'message' => 'Rate limit exceeded'],
                ],
            ], 429, ['Retry-After' => '60']),
        ]);
    }

    /**
     * Mock DigitalOcean API responses
     */
    protected function mockDigitalOceanApi(): void
    {
        Http::fake([
            'api.digitalocean.com/v2/droplets' => Http::response([
                'droplets' => [
                    [
                        'id' => 12345,
                        'name' => 'test-droplet',
                        'status' => 'active',
                        'networks' => [
                            'v4' => [
                                ['ip_address' => '192.168.100.10', 'type' => 'public'],
                            ],
                        ],
                    ],
                ],
                'links' => [],
                'meta' => ['total' => 1],
            ], 200),

            'api.digitalocean.com/v2/droplets/*' => Http::response([
                'droplet' => [
                    'id' => 12345,
                    'name' => 'test-droplet',
                    'status' => 'active',
                ],
            ], 200),
        ]);
    }

    /**
     * Mock Let's Encrypt ACME API
     */
    protected function mockLetsEncryptApi(): void
    {
        Http::fake([
            'acme-v02.api.letsencrypt.org/*' => Http::response([
                'status' => 'valid',
                'expires' => now()->addDays(90)->toISOString(),
                'certificate' => '-----BEGIN CERTIFICATE-----\ntest-certificate\n-----END CERTIFICATE-----',
            ], 200),
        ]);
    }

    /**
     * Mock PowerDNS API responses
     */
    protected function mockPowerDnsApi(): void
    {
        Http::fake([
            '*/api/v1/servers/localhost/zones' => Http::response([
                [
                    'name' => 'example.com.',
                    'type' => 'Zone',
                    'kind' => 'Master',
                    'serial' => 2023010101,
                ],
            ], 200),

            '*/api/v1/servers/localhost/zones/*' => Http::response([
                'name' => 'example.com.',
                'type' => 'Zone',
                'kind' => 'Master',
                'rrsets' => [
                    [
                        'name' => 'www.example.com.',
                        'type' => 'A',
                        'records' => [
                            ['content' => '192.168.100.10', 'disabled' => false],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Mock external service health checks
     */
    protected function mockHealthChecks(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'ok'], 200),
            '*/api/health' => Http::response(['status' => 'healthy'], 200),
            '*/ping' => Http::response('pong', 200),
            '*/status' => Http::response([
                'status' => 'up',
                'services' => [
                    'database' => 'up',
                    'redis' => 'up',
                    'filesystem' => 'up',
                ],
            ], 200),
        ]);
    }

    /**
     * Mock external service failures
     */
    protected function mockServiceFailures(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'error'], 500),
            '*/api/health' => Http::response(['status' => 'unhealthy'], 503),
            '*/ping' => Http::response('Service Unavailable', 503),
            '*/status' => Http::response([
                'status' => 'down',
                'error' => 'Service temporarily unavailable',
            ], 503),
        ]);
    }

    /**
     * Mock webhook delivery services
     */
    protected function mockWebhookServices(): void
    {
        Http::fake([
            '*/webhook' => Http::response(['received' => true], 200),
            '*/webhooks/*' => Http::response(['delivered' => true], 200),
            '*/api/webhooks/deliver' => Http::response([
                'id' => 'webhook-'.uniqid(),
                'status' => 'delivered',
                'delivered_at' => now()->toISOString(),
            ], 200),
        ]);
    }

    /**
     * Mock SSL certificate validation services
     */
    protected function mockSslValidationServices(): void
    {
        Http::fake([
            '*/ssl-check/*' => Http::response([
                'valid' => true,
                'expires_at' => now()->addDays(30)->toISOString(),
                'issuer' => 'Let\'s Encrypt',
                'chain_valid' => true,
            ], 200),

            'crt.sh/*' => Http::response([
                [
                    'id' => 12345,
                    'name_value' => 'example.com',
                    'not_before' => now()->subDays(30)->toISOString(),
                    'not_after' => now()->addDays(60)->toISOString(),
                ],
            ], 200),
        ]);
    }

    /**
     * Mock DNS propagation checking services
     */
    protected function mockDnsPropagationServices(): void
    {
        Http::fake([
            'dns.google/resolve*' => Http::response([
                'Status' => 0,
                'Answer' => [
                    [
                        'name' => 'example.com.',
                        'type' => 1,
                        'data' => '192.168.100.10',
                    ],
                ],
            ], 200),

            'cloudflare-dns.com/dns-query*' => Http::response([
                'Status' => 0,
                'Answer' => [
                    [
                        'name' => 'example.com.',
                        'type' => 1,
                        'data' => '192.168.100.10',
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Mock email delivery services (like SendGrid, Mailgun)
     */
    protected function mockEmailServices(): void
    {
        Http::fake([
            'api.sendgrid.com/v3/mail/send' => Http::response(['message' => 'success'], 202),
            'api.mailgun.net/v3/*/messages' => Http::response([
                'message' => 'Queued. Thank you.',
                'id' => '<test-message-id@example.com>',
            ], 200),
            'api.postmarkapp.com/email' => Http::response([
                'To' => 'test@example.com',
                'SubmittedAt' => now()->toISOString(),
                'MessageID' => 'test-message-id',
            ], 200),
        ]);
    }

    /**
     * Mock backup storage services (S3, etc.)
     */
    protected function mockBackupServices(): void
    {
        Http::fake([
            's3.amazonaws.com/*' => Http::response('', 200),
            '*.s3.amazonaws.com/*' => Http::response('', 200),
            'storage.googleapis.com/*' => Http::response('', 200),
        ]);
    }

    /**
     * Mock monitoring and alerting services
     */
    protected function mockMonitoringServices(): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response([
                'stat' => 'ok',
                'monitors' => [
                    ['status' => 2, 'friendly_name' => 'Test Monitor'],
                ],
            ], 200),

            'api.pingdom.com/*' => Http::response([
                'checks' => [
                    ['status' => 'up', 'name' => 'Test Check'],
                ],
            ], 200),

            'hooks.slack.com/services/*' => Http::response('ok', 200),
            'discord.com/api/webhooks/*' => Http::response(['id' => 'message-id'], 200),
        ]);
    }

    /**
     * Assert that external HTTP requests were made
     */
    protected function assertHttpRequestMade(string $url, string $method = 'GET'): void
    {
        Http::assertSent(function ($request) use ($url, $method) {
            return $request->url() === $url && $request->method() === strtoupper($method);
        });
    }

    /**
     * Assert that no external HTTP requests were made
     */
    protected function assertNoHttpRequestsMade(): void
    {
        Http::assertNothingSent();
    }

    /**
     * Get recorded HTTP requests for inspection
     */
    protected function getHttpRequests(): array
    {
        return Http::recorded();
    }

    /**
     * Mock generic REST API responses
     */
    protected function mockRestApi(string $baseUrl, array $responses): void
    {
        $fakeResponses = [];

        foreach ($responses as $endpoint => $response) {
            $fullUrl = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
            $fakeResponses[$fullUrl] = Http::response($response['body'] ?? [], $response['status'] ?? 200);
        }

        Http::fake($fakeResponses);
    }

    /**
     * Mock timeout scenarios for external services
     */
    protected function mockServiceTimeouts(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });
    }
}
