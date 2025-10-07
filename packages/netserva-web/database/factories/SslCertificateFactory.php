<?php

namespace NetServa\Web\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Web\Models\SslCertificate;
use NetServa\Web\Models\SslCertificateAuthority;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Web\Models\SslCertificate>
 */
class SslCertificateFactory extends Factory
{
    protected $model = SslCertificate::class;

    public function definition(): array
    {
        $domains = [
            'example.com',
            'test.example.com',
            'api.example.com',
            'mail.example.com',
            'www.example.com',
        ];

        $commonName = $this->faker->randomElement($domains);
        $isWildcard = $this->faker->boolean(20);

        if ($isWildcard) {
            $commonName = '*.example.com';
        }

        $notValidBefore = \Carbon\Carbon::instance($this->faker->dateTimeBetween('-1 month', 'now'));
        $notValidAfter = \Carbon\Carbon::instance($this->faker->dateTimeBetween('+1 month', '+1 year'));

        return [
            // Core certificate identity
            'common_name' => $commonName,
            'subject_alternative_names' => $this->faker->boolean(70) ?
                [$this->faker->randomElement($domains), $this->faker->randomElement($domains)] : null,
            'certificate_type' => $this->faker->randomElement(array_keys(SslCertificate::CERTIFICATE_TYPES)),
            'ssl_certificate_authority_id' => SslCertificateAuthority::factory(),

            // Certificate content (simplified for testing)
            'certificate_pem' => $this->generateFakeCertPem(),
            'certificate_chain_pem' => $this->faker->boolean(80) ? $this->generateFakeCertPem() : null,
            'private_key_pem' => $this->generateFakePrivateKey(),

            // Certificate properties
            'key_type' => $this->faker->randomElement(array_keys(SslCertificate::KEY_TYPES)),
            'key_size' => $this->faker->randomElement([2048, 4096]),

            // Validity period (REQUIRED)
            'not_valid_before' => $notValidBefore,
            'not_valid_after' => $notValidAfter,

            // Auto-renewal configuration
            'auto_renew' => $this->faker->boolean(80),
            'renew_days_before_expiry' => $this->faker->randomElement([7, 14, 30]),
            'next_renewal_attempt_at' => $this->faker->boolean(50) ?
                $this->faker->dateTimeBetween('now', '+2 months') : null,

            // Status
            'status' => $this->faker->randomElement(array_keys(SslCertificate::STATUSES)),

            // Certificate flags
            'is_wildcard' => $isWildcard,

            // Administrative
            'notes' => $this->faker->boolean(30) ? $this->faker->sentence() : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'not_valid_before' => now()->subDays(7),
            'not_valid_after' => now()->addMonths(3),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'not_valid_before' => now()->subMonths(6),
            'not_valid_after' => now()->subDays(1),
        ]);
    }

    public function wildcard(): static
    {
        return $this->state(fn (array $attributes) => [
            'common_name' => '*.example.com',
            'certificate_type' => 'wildcard',
            'is_wildcard' => true,
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'not_valid_before' => now()->subMonths(2),
            'not_valid_after' => now()->addDays(15), // expires in 15 days
            'auto_renew' => true,
            'renew_days_before_expiry' => 30,
        ]);
    }

    private function generateFakeCertPem(): string
    {
        return "-----BEGIN CERTIFICATE-----\n".
               "FAKE CERTIFICATE DATA FOR TESTING\n".
               base64_encode($this->faker->randomAscii(200))."\n".
               '-----END CERTIFICATE-----';
    }

    private function generateFakePrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n".
               "FAKE PRIVATE KEY DATA FOR TESTING\n".
               base64_encode($this->faker->randomAscii(200))."\n".
               '-----END PRIVATE KEY-----';
    }

    private function generateFakeCSR(): string
    {
        return "-----BEGIN CERTIFICATE REQUEST-----\n".
               "FAKE CSR DATA FOR TESTING\n".
               base64_encode($this->faker->randomAscii(150))."\n".
               '-----END CERTIFICATE REQUEST-----';
    }
}
