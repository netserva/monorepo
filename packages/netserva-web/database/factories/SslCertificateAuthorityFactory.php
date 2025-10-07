<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Web\Models\SslCertificateAuthority;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Web\Models\SslCertificateAuthority>
 */
class SslCertificateAuthorityFactory extends Factory
{
    protected $model = SslCertificateAuthority::class;

    public function definition(): array
    {
        $caType = $this->faker->randomElement(array_keys(SslCertificateAuthority::CA_TYPES));

        return [
            // Basic CA information
            'name' => $this->faker->randomElement([
                "Let's Encrypt",
                'Buypass',
                'ZeroSSL',
                'Custom CA',
                'Development CA',
            ]),
            'ca_type' => $caType,
            'acme_directory_url' => $this->getAcmeDirectoryForType($caType),
            'account_email' => $this->faker->email(),
            'ca_certificate' => $caType === 'custom' ? $this->generateFakeCaCert() : null,

            // Configuration
            'is_active' => $this->faker->boolean(85),
            'is_default' => false, // Will be set explicitly when needed
            'supports_wildcard' => $this->faker->boolean(80),

            // Authentication
            'account_key' => $this->generateFakeAccountKey(),
            'account_url' => $this->faker->boolean(70) ? $this->faker->url() : null,
            'auth_config' => $this->faker->boolean(60) ? [
                'key_type' => 'rsa',
                'key_size' => 2048,
                'challenges' => ['http-01', 'dns-01'],
            ] : [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function letsencrypt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Let's Encrypt",
            'ca_type' => 'letsencrypt',
            'acme_directory_url' => SslCertificateAuthority::ACME_DIRECTORIES['letsencrypt_prod'],
            'supports_wildcard' => true,
            'is_active' => true,
        ]);
    }

    public function letsencryptStaging(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Let's Encrypt (Staging)",
            'ca_type' => 'letsencrypt',
            'acme_directory_url' => SslCertificateAuthority::ACME_DIRECTORIES['letsencrypt_staging'],
            'supports_wildcard' => true,
            'is_active' => true,
        ]);
    }

    public function buypass(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Buypass',
            'ca_type' => 'buypass',
            'acme_directory_url' => SslCertificateAuthority::ACME_DIRECTORIES['buypass_prod'],
            'supports_wildcard' => false,
            'is_active' => true,
        ]);
    }

    public function zerossl(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'ZeroSSL',
            'ca_type' => 'zerossl',
            'acme_directory_url' => SslCertificateAuthority::ACME_DIRECTORIES['zerossl_prod'],
            'supports_wildcard' => true,
            'is_active' => true,
        ]);
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Custom CA',
            'ca_type' => 'custom',
            'acme_directory_url' => null,
            'ca_certificate' => $this->generateFakeCaCert(),
            'supports_wildcard' => true,
        ]);
    }

    public function selfSigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Self-Signed CA',
            'ca_type' => 'self_signed',
            'acme_directory_url' => null,
            'ca_certificate' => $this->generateFakeCaCert(),
            'supports_wildcard' => true,
        ]);
    }

    private function getAcmeDirectoryForType(string $caType): ?string
    {
        return match ($caType) {
            'letsencrypt' => SslCertificateAuthority::ACME_DIRECTORIES['letsencrypt_prod'],
            'buypass' => SslCertificateAuthority::ACME_DIRECTORIES['buypass_prod'],
            'zerossl' => SslCertificateAuthority::ACME_DIRECTORIES['zerossl_prod'],
            default => null,
        };
    }

    private function generateFakeAccountKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n".
               "FAKE ACCOUNT KEY FOR TESTING\n".
               base64_encode($this->faker->randomAscii(200))."\n".
               '-----END PRIVATE KEY-----';
    }

    private function generateFakeCaCert(): string
    {
        return "-----BEGIN CERTIFICATE-----\n".
               "FAKE CA CERTIFICATE FOR TESTING\n".
               base64_encode($this->faker->randomAscii(300))."\n".
               '-----END CERTIFICATE-----';
    }
}
