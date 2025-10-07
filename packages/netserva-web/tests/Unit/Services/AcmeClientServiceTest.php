<?php

namespace NetServa\Web\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use NetServa\Web\Models\SslCertificateAuthority;
use NetServa\Web\Services\AcmeClientService;
use Tests\TestCase;

class AcmeClientServiceTest extends TestCase
{
    protected AcmeClientService $service;

    protected SslCertificateAuthority $ca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ca = SslCertificateAuthority::factory()->create([
            'name' => 'Let\'s Encrypt Staging',
            'acme_directory_url' => 'https://acme-staging-v02.api.letsencrypt.org/directory',
        ]);

        $this->service = new AcmeClientService;
    }

    /** @test */
    public function it_can_request_certificate_successfully()
    {
        Http::fake([
            '*/directory' => Http::response([
                'newAccount' => 'https://acme-staging-v02.api.letsencrypt.org/acme/new-acct',
                'newOrder' => 'https://acme-staging-v02.api.letsencrypt.org/acme/new-order',
                'revokeCert' => 'https://acme-staging-v02.api.letsencrypt.org/acme/revoke-cert',
            ], 200),
            '*/new-acct' => Http::response([
                'status' => 'valid',
                'contact' => ['mailto:admin@example.com'],
            ], 201, ['Location' => 'https://acme/acct/123']),
            '*/new-order' => Http::response([
                'status' => 'pending',
                'authorizations' => ['https://acme/authz/1'],
                'finalize' => 'https://acme/order/1/finalize',
                'certificate' => 'https://acme/cert/1',
            ], 201),
            '*/authz/*' => Http::response([
                'status' => 'valid',
                'identifier' => ['type' => 'dns', 'value' => 'example.com'],
                'challenges' => [
                    ['type' => 'http-01', 'url' => 'https://acme/chall/1', 'token' => 'test-token'],
                ],
            ], 200),
            '*/finalize' => Http::response([
                'status' => 'valid',
                'certificate' => 'https://acme/cert/1',
            ], 200),
            '*/cert/*' => Http::response('-----BEGIN CERTIFICATE-----
MIIFakeTestCertificate
-----END CERTIFICATE-----', 200),
        ]);

        $result = $this->service->requestCertificate(
            $this->ca,
            'example.com',
            ['www.example.com'],
            '-----BEGIN CERTIFICATE REQUEST-----MIITestCSR-----END CERTIFICATE REQUEST-----',
            ['http-01']
        );

        expect($result)
            ->toHaveKey('success', true)
            ->toHaveKey('certificate')
            ->toHaveKey('chain');
    }

    /** @test */
    public function it_handles_certificate_request_failures()
    {
        Http::fake([
            '*' => Http::response(['error' => 'Invalid credentials'], 401),
        ]);

        $result = $this->service->requestCertificate(
            $this->ca,
            'example.com',
            [],
            'fake-csr',
            ['http-01']
        );

        expect($result)
            ->toHaveKey('success', false)
            ->toHaveKey('error');
    }

    /** @test */
    public function it_can_revoke_certificate()
    {
        Http::fake([
            '*/directory' => Http::response([
                'revokeCert' => 'https://acme-staging-v02.api.letsencrypt.org/acme/revoke-cert',
            ], 200),
            '*/revoke-cert' => Http::response('', 200),
        ]);

        $result = $this->service->revokeCertificate(
            $this->ca,
            '-----BEGIN CERTIFICATE-----MIITest-----END CERTIFICATE-----',
            'keyCompromise'
        );

        expect($result)->toBeTrue();
    }

    /** @test */
    public function it_handles_revocation_failures()
    {
        Http::fake([
            '*/directory' => Http::response([
                'revokeCert' => 'https://acme-staging-v02.api.letsencrypt.org/acme/revoke-cert',
            ], 200),
            '*/revoke-cert' => Http::response(['error' => 'Certificate not found'], 404),
        ]);

        $result = $this->service->revokeCertificate(
            $this->ca,
            'invalid-cert',
            'cessationOfOperation'
        );

        expect($result)->toBeFalse();
    }

    /** @test */
    public function it_supports_multiple_validation_methods()
    {
        Http::fake([
            '*/directory' => Http::response([
                'newAccount' => 'https://acme/new-acct',
                'newOrder' => 'https://acme/new-order',
            ], 200),
            '*/new-acct' => Http::response(['status' => 'valid'], 201, ['Location' => 'https://acme/acct/1']),
            '*/new-order' => Http::response([
                'authorizations' => ['https://acme/authz/1'],
                'finalize' => 'https://acme/order/1/finalize',
                'certificate' => 'https://acme/cert/1',
            ], 201),
            '*/authz/*' => Http::response([
                'status' => 'valid',
                'challenges' => [
                    ['type' => 'http-01', 'token' => 'http-token'],
                    ['type' => 'dns-01', 'token' => 'dns-token'],
                ],
            ], 200),
            '*/finalize' => Http::response(['certificate' => 'https://acme/cert/1'], 200),
            '*/cert/*' => Http::response('-----BEGIN CERTIFICATE-----Test-----END CERTIFICATE-----', 200),
        ]);

        // Test DNS-01 validation
        $result = $this->service->requestCertificate(
            $this->ca,
            'example.com',
            [],
            'test-csr',
            ['dns-01']
        );

        expect($result)->toHaveKey('success', true);
    }

    /** @test */
    public function it_handles_rate_limiting()
    {
        Http::fake([
            '*' => Http::response([
                'type' => 'urn:ietf:params:acme:error:rateLimited',
                'detail' => 'Too many requests',
            ], 429),
        ]);

        $result = $this->service->requestCertificate(
            $this->ca,
            'example.com',
            [],
            'test-csr'
        );

        expect($result)
            ->toHaveKey('success', false)
            ->toHaveKey('error');
    }
}
