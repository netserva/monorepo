<?php

namespace NetServa\Dns\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\PowerDnsService;
use NetServa\Dns\Services\PowerDnsTunnelService;
use Tests\TestCase;

class PowerDnsServiceTest extends TestCase
{
    protected PowerDnsService $service;

    protected DnsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = DnsProvider::factory()->create([
            'type' => 'powerdns',
            'api_endpoint' => 'http://localhost:8081/api/v1',
            'api_key' => 'test-api-key',
        ]);

        $tunnelService = $this->app->make(PowerDnsTunnelService::class);
        $this->service = new PowerDnsService($tunnelService);
    }

    /** @test */
    public function it_can_get_servers()
    {
        Http::fake([
            '*' => Http::response([
                ['id' => 'localhost', 'type' => 'Server', 'daemon_type' => 'authoritative'],
            ], 200),
        ]);

        $result = $this->service->getServers($this->provider);

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('servers');
    }

    /** @test */
    public function it_can_list_zones()
    {
        Http::fake([
            '*/zones*' => Http::response([
                ['id' => 'example.com.', 'name' => 'example.com.', 'type' => 'Zone'],
            ], 200),
        ]);

        $result = $this->service->listZones($this->provider);

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('zones');
    }

    /** @test */
    public function it_can_create_zone()
    {
        Http::fake([
            '*/zones*' => Http::response([
                'id' => 'test.example.com.',
                'name' => 'test.example.com.',
                'type' => 'Zone',
                'kind' => 'Native',
            ], 201),
        ]);

        $result = $this->service->createZone($this->provider, 'test.example.com', 'Native');

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('zone');
    }

    /** @test */
    public function it_can_delete_zone()
    {
        Http::fake([
            '*/zones/*' => Http::response('', 204),
        ]);

        $result = $this->service->deleteZone($this->provider, 'test.example.com');

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_can_get_zone_details()
    {
        Http::fake([
            '*/zones/*' => Http::response([
                'id' => 'example.com.',
                'name' => 'example.com.',
                'type' => 'Zone',
                'kind' => 'Native',
                'serial' => 2025010501,
                'rrsets' => [],
            ], 200),
        ]);

        $result = $this->service->getZone($this->provider, 'example.com');

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('zone')
            ->and($result['zone'])->toHaveKey('serial');
    }

    /** @test */
    public function it_can_add_record()
    {
        Http::fake([
            '*/zones/*' => Http::response('', 204),
        ]);

        $result = $this->service->addRecord(
            $this->provider,
            'example.com',
            'test.example.com.',
            'A',
            '192.168.1.100',
            300
        );

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_can_update_record()
    {
        Http::fake([
            '*/zones/*' => Http::response('', 204),
        ]);

        $result = $this->service->updateRecord(
            $this->provider,
            'example.com',
            'test.example.com.',
            'A',
            '192.168.1.200',
            300
        );

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_can_delete_record()
    {
        Http::fake([
            '*/zones/*' => Http::response('', 204),
        ]);

        $result = $this->service->deleteRecord(
            $this->provider,
            'example.com',
            'test.example.com.',
            'A'
        );

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->service->getServers($this->provider);

        expect($result)
            ->toHaveKey('success', false)
            ->toHaveKey('message');
    }

    /** @test */
    public function it_can_notify_slaves()
    {
        Http::fake([
            '*/zones/*/notify*' => Http::response(['result' => 'Notification sent'], 200),
        ]);

        $result = $this->service->notifySecondaries($this->provider, 'example.com');

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_can_get_zone_statistics()
    {
        Http::fake([
            '*/statistics*' => Http::response([
                'queries' => 12345,
                'cache_hit_ratio' => 0.85,
            ], 200),
        ]);

        $result = $this->service->getStatistics($this->provider);

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('statistics');
    }
}
