<?php

namespace NetServa\Dns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Dns\Models\DomainRegistrar;
use NetServa\Dns\Models\DomainRegistration;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Nsis\DomainManager\Models\DomainRegistration>
 */
class DomainRegistrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DomainRegistration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrationDate = fake()->dateTimeBetween('-2 years', '-1 month');
        $expiryDate = (clone $registrationDate)->modify('+1 year');
        $renewalDate = (clone $expiryDate)->modify('-30 days');

        $tlds = ['com', 'net', 'org', 'io', 'co', 'app', 'dev', 'tech'];
        $statuses = ['active', 'inactive', 'expired', 'suspended'];

        return [
            'infrastructure_node_id' => InfrastructureNode::factory(),
            'domain_registrar_id' => DomainRegistrar::factory(),
            'domain_name' => fake()->domainWord().'.'.fake()->randomElement($tlds),
            'registration_date' => $registrationDate,
            'expiry_date' => $expiryDate,
            'renewal_date' => $renewalDate,
            'auto_renew' => fake()->boolean(70), // 70% chance of auto-renew
            'status' => fake()->randomElement($statuses),
            'registrant_contact' => [
                'name' => fake()->name(),
                'organization' => fake()->optional()->company(),
                'email' => fake()->email(),
                'phone' => fake()->phoneNumber(),
                'address' => [
                    'street' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'state' => fake()->state(),
                    'postal_code' => fake()->postcode(),
                    'country' => fake()->countryCode(),
                ],
            ],
            'nameservers' => [
                'ns1.'.fake()->domainName(),
                'ns2.'.fake()->domainName(),
            ],
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the domain registration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the domain registration is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expiry_date' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Indicate that the domain registration has auto-renew enabled.
     */
    public function autoRenew(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_renew' => true,
        ]);
    }

    /**
     * Indicate that the domain registration expires soon.
     */
    public function expiringSoon(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->addDays(fake()->numberBetween(1, $days)),
            'status' => 'active',
        ]);
    }

    /**
     * Create a domain registration with a specific TLD.
     */
    public function withTld(string $tld): static
    {
        return $this->state(fn (array $attributes) => [
            'domain_name' => fake()->domainWord().'.'.$tld,
        ]);
    }

    /**
     * Create a domain registration with custom nameservers.
     */
    public function withNameservers(array $nameservers): static
    {
        return $this->state(fn (array $attributes) => [
            'nameservers' => $nameservers,
        ]);
    }

    /**
     * Create a domain registration with minimal contact information.
     */
    public function minimalContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'registrant_contact' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
            ],
        ]);
    }

    /**
     * Create a domain registration with full contact information.
     */
    public function fullContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'registrant_contact' => [
                'name' => fake()->name(),
                'organization' => fake()->company(),
                'email' => fake()->email(),
                'phone' => fake()->phoneNumber(),
                'fax' => fake()->optional()->phoneNumber(),
                'address' => [
                    'street' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'state' => fake()->state(),
                    'postal_code' => fake()->postcode(),
                    'country' => fake()->countryCode(),
                ],
                'admin_contact' => [
                    'name' => fake()->name(),
                    'email' => fake()->email(),
                    'phone' => fake()->phoneNumber(),
                ],
                'tech_contact' => [
                    'name' => fake()->name(),
                    'email' => fake()->email(),
                    'phone' => fake()->phoneNumber(),
                ],
                'billing_contact' => [
                    'name' => fake()->name(),
                    'email' => fake()->email(),
                    'phone' => fake()->phoneNumber(),
                ],
            ],
        ]);
    }

    /**
     * Create a domain registration for a specific registrar.
     */
    public function forRegistrar(DomainRegistrar $registrar): static
    {
        return $this->state(fn (array $attributes) => [
            'domain_registrar_id' => $registrar->id,
        ]);
    }

    /**
     * Create a domain registration for a specific infrastructure node.
     */
    public function forInfrastructureNode(InfrastructureNode $node): static
    {
        return $this->state(fn (array $attributes) => [
            'infrastructure_node_id' => $node->id,
        ]);
    }

    /**
     * Create a domain registration without an infrastructure node.
     */
    public function withoutInfrastructureNode(): static
    {
        return $this->state(fn (array $attributes) => [
            'infrastructure_node_id' => null,
        ]);
    }

    /**
     * Create a recently registered domain.
     */
    public function recentlyRegistered(): static
    {
        $registrationDate = fake()->dateTimeBetween('-30 days', 'now');

        return $this->state(fn (array $attributes) => [
            'registration_date' => $registrationDate,
            'expiry_date' => (clone $registrationDate)->modify('+1 year'),
            'renewal_date' => (clone $registrationDate)->modify('+11 months'),
            'status' => 'active',
        ]);
    }

    /**
     * Create a domain registration with specific dates.
     */
    public function withDates(\DateTime $registration, \DateTime $expiry, ?\DateTime $renewal = null): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_date' => $registration,
            'expiry_date' => $expiry,
            'renewal_date' => $renewal ?? (clone $expiry)->modify('-30 days'),
        ]);
    }
}
