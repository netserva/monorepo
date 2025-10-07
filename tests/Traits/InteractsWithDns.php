<?php

namespace Tests\Traits;

use Mockery;
use Ns\Dns\Models\DnsProvider;
use Ns\Dns\Models\DnsRecord;
use Ns\Dns\Models\DnsZone;
use Ns\Dns\Services\DnsService;

trait InteractsWithDns
{
    /**
     * Create a mock DNS provider for testing
     */
    protected function createMockDnsProvider(array $attributes = []): DnsProvider
    {
        return DnsProvider::factory()->create(array_merge([
            'name' => 'Test DNS Provider',
            'provider_type' => 'cloudflare',
            'api_endpoint' => 'https://api.cloudflare.com/client/v4',
            'is_active' => true,
            'credentials' => [
                'api_token' => 'test-token-'.uniqid(),
                'email' => 'test@example.com',
            ],
        ], $attributes));
    }

    /**
     * Create a mock DNS zone for testing
     */
    protected function createMockDnsZone(?DnsProvider $provider = null, array $attributes = []): DnsZone
    {
        if (! $provider) {
            $provider = $this->createMockDnsProvider();
        }

        return DnsZone::factory()->create(array_merge([
            'dns_provider_id' => $provider->id,
            'name' => 'example.com',
            'status' => 'active',
            'ttl' => 3600,
            'records_count' => 0,
        ], $attributes));
    }

    /**
     * Create a mock DNS record for testing
     */
    protected function createMockDnsRecord(?DnsZone $zone = null, array $attributes = []): DnsRecord
    {
        if (! $zone) {
            $zone = $this->createMockDnsZone();
        }

        return DnsRecord::factory()->create(array_merge([
            'dns_zone_id' => $zone->id,
            'name' => 'www',
            'type' => 'A',
            'content' => '192.168.100.10',
            'ttl' => 3600,
            'priority' => null,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Mock successful DNS API responses
     */
    protected function mockSuccessfulDnsApi(): void
    {
        $this->mock(DnsService::class, function ($mock) {
            $mock->shouldReceive('validateConnection')
                ->andReturn(true);

            $mock->shouldReceive('createZone')
                ->andReturn([
                    'success' => true,
                    'zone_id' => 'test-zone-'.uniqid(),
                    'name_servers' => [
                        'ns1.example.com',
                        'ns2.example.com',
                    ],
                ]);

            $mock->shouldReceive('createRecord')
                ->andReturn([
                    'success' => true,
                    'record_id' => 'test-record-'.uniqid(),
                ]);

            $mock->shouldReceive('updateRecord')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('deleteRecord')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('getRecords')
                ->andReturn([
                    'success' => true,
                    'records' => [],
                ]);
        });
    }

    /**
     * Mock failed DNS API responses
     */
    protected function mockFailedDnsApi(string $errorMessage = 'API request failed'): void
    {
        $this->mock(DnsService::class, function ($mock) use ($errorMessage) {
            $mock->shouldReceive('validateConnection')
                ->andReturn(false);

            $mock->shouldReceive('createZone')
                ->andReturn([
                    'success' => false,
                    'error' => $errorMessage,
                ]);

            $mock->shouldReceive('createRecord')
                ->andReturn([
                    'success' => false,
                    'error' => $errorMessage,
                ]);

            $mock->shouldReceive('getLastError')
                ->andReturn($errorMessage);
        });
    }

    /**
     * Mock DNS record validation
     */
    protected function mockDnsRecordValidation(bool $isValid = true, array $validationErrors = []): void
    {
        $this->mock(DnsService::class, function ($mock) use ($isValid, $validationErrors) {
            $mock->shouldReceive('validateRecord')
                ->andReturn($isValid);

            if (! $isValid) {
                $mock->shouldReceive('getValidationErrors')
                    ->andReturn($validationErrors);
            }

            $mock->shouldReceive('validateRecordType')
                ->andReturn($isValid);

            $mock->shouldReceive('validateRecordContent')
                ->andReturn($isValid);
        });
    }

    /**
     * Mock DNS propagation check
     */
    protected function mockDnsPropagationCheck(bool $isPropagated = true): void
    {
        $this->mock(DnsService::class, function ($mock) use ($isPropagated) {
            $mock->shouldReceive('checkPropagation')
                ->andReturn([
                    'propagated' => $isPropagated,
                    'servers_checked' => 4,
                    'servers_updated' => $isPropagated ? 4 : 2,
                    'results' => [
                        '8.8.8.8' => $isPropagated,
                        '1.1.1.1' => $isPropagated,
                        '208.67.222.222' => $isPropagated,
                        '156.154.70.1' => $isPropagated ? true : false,
                    ],
                ]);
        });
    }

    /**
     * Create standard DNS record types for testing
     */
    protected function createStandardDnsRecords(DnsZone $zone): array
    {
        return [
            'a_record' => $this->createMockDnsRecord($zone, [
                'name' => 'www',
                'type' => 'A',
                'content' => '192.168.100.10',
            ]),
            'aaaa_record' => $this->createMockDnsRecord($zone, [
                'name' => 'www',
                'type' => 'AAAA',
                'content' => '2001:db8::1',
            ]),
            'mx_record' => $this->createMockDnsRecord($zone, [
                'name' => '@',
                'type' => 'MX',
                'content' => 'mail.example.com',
                'priority' => 10,
            ]),
            'cname_record' => $this->createMockDnsRecord($zone, [
                'name' => 'blog',
                'type' => 'CNAME',
                'content' => 'www.example.com',
            ]),
            'txt_record' => $this->createMockDnsRecord($zone, [
                'name' => '@',
                'type' => 'TXT',
                'content' => 'v=spf1 include:_spf.google.com ~all',
            ]),
        ];
    }

    /**
     * Assert DNS API was called with correct parameters
     */
    protected function assertDnsApiCalled(string $method, array $expectedParams = []): void
    {
        $container = Mockery::getContainer();
        $mocks = $container->getMocks();

        foreach ($mocks as $mock) {
            if ($mock instanceof DnsService) {
                $expectations = $mock->mockery_getExpectationsFor($method);
                $this->assertNotEmpty($expectations, "Expected {$method} to be called on DNS service");

                if (! empty($expectedParams)) {
                    foreach ($expectations as $expectation) {
                        $this->assertTrue(
                            $expectation->matchArgs($expectedParams),
                            "Expected {$method} to be called with correct parameters"
                        );
                    }
                }

                return;
            }
        }

        $this->fail('DNS service mock not found');
    }

    /**
     * Create bulk DNS records for performance testing
     */
    protected function createBulkDnsRecords(DnsZone $zone, int $count = 100): array
    {
        $records = [];

        for ($i = 1; $i <= $count; $i++) {
            $records[] = $this->createMockDnsRecord($zone, [
                'name' => "test{$i}",
                'type' => 'A',
                'content' => "192.168.100.{$i}",
            ]);
        }

        return $records;
    }

    /**
     * Mock rate limiting behavior
     */
    protected function mockDnsApiRateLimit(int $retryAfter = 60): void
    {
        $this->mock(DnsService::class, function ($mock) use ($retryAfter) {
            $mock->shouldReceive('createRecord')
                ->andReturn([
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'rate_limited' => true,
                    'retry_after' => $retryAfter,
                ]);

            $mock->shouldReceive('isRateLimited')
                ->andReturn(true);

            $mock->shouldReceive('getRetryAfter')
                ->andReturn($retryAfter);
        });
    }
}
