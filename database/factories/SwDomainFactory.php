<?php

namespace Database\Factories;

use App\Models\SwDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SwDomain>
 */
class SwDomainFactory extends Factory
{
    protected $model = SwDomain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_name' => fake()->domainName(),
            'domain_roid' => fake()->uuid().'-VRSN',
            'registry_id' => (string) fake()->numberBetween(1, 100),
            'domain_status' => fake()->randomElement([
                'ok',
                'clientTransferProhibited',
                'serverTransferProhibited',
            ]),
            'lifecycle_status' => 'active',
            'domain_expiry' => now()->addYear(),
            'domain_registered' => now()->subYears(2),
            'created_date' => now()->subYears(2),
            'registrant' => fake()->company(),
            'domain_password' => fake()->password(16),
            'nameservers' => [
                'ns1.'.fake()->domainName(),
                'ns2.'.fake()->domainName(),
            ],
            'dns_config_type' => 0,
            'dns_management_enabled' => false,
            'email_forwarding_enabled' => false,
            'id_protection_enabled' => fake()->boolean(),
            'is_premium' => false,
            'auto_renew' => fake()->boolean(70), // 70% true
            'do_not_renew' => false,
            'is_active' => true,
            'is_synced' => true,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Indicate that the domain is transferred away
     */
    public function transferredAway(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'transferred_away',
            'domain_status' => 'Transferred Away',
            'is_active' => false,
            'error_message' => 'Domain Info Failed - Domain Does Not Exist',
        ]);
    }

    /**
     * Indicate that the domain is in grace period
     */
    public function gracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'grace',
            'domain_expiry' => now()->subDays(5),
            'grace_period_days' => 30,
            'grace_period_fee' => 10.00,
        ]);
    }

    /**
     * Indicate that the domain is in redemption period
     */
    public function redemptionPeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'redemption',
            'domain_expiry' => now()->subDays(35),
            'redemption_period_days' => 30,
            'redemption_period_fee' => 150.00,
        ]);
    }

    /**
     * Indicate that the domain is expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'expired',
            'domain_expiry' => now()->subDays(65),
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the domain is pending registration
     */
    public function pendingRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'pending_registration',
            'domain_status' => 'pendingCreate',
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the domain has DNSSEC enabled
     */
    public function withDnssec(): static
    {
        return $this->state(fn (array $attributes) => [
            'ds_data' => [
                [
                    'keyTag' => fake()->numberBetween(1000, 9999),
                    'algorithm' => 8,
                    'digestType' => 2,
                    'digest' => fake()->sha256(),
                ],
            ],
        ]);
    }

    /**
     * Indicate that the domain is a .au domain
     */
    public function auDomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'domain_name' => fake()->domainWord().'.com.au',
            'registry_id' => '85', // .au registry
        ]);
    }

    /**
     * Indicate that the domain has auto-renewal enabled
     */
    public function autoRenew(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_renew' => true,
            'do_not_renew' => false,
        ]);
    }

    /**
     * Indicate that the domain has ID protection
     */
    public function withIdProtection(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_protection_enabled' => true,
            'id_protect' => true,
        ]);
    }
}
